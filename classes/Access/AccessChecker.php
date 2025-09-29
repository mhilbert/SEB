<?php

/**
 * This file is part of the SEB-Plugin for ILIAS.
 *
 * SEB-Plugin for ILIAS is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * SEB-Plugin for ILIAS is distributed in the hope that
 * it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with SEB-Plugin for ILIAS.  If not,
 * see <http://www.gnu.org/licenses/>.
 *
 * The SEB-Plugin for ILIAS is a refactoring of a previous Plugin by Stefan
 * Schneider that can be found on Github
 * <https://github.com/hrz-unimr/Ilias.SEBPlugin>
 */

declare(strict_types=1);

namespace kergomard\SEB\Access;

use kergomard\SEB\Config\Configuration;
use kergomard\SEB\Config\ObjectSpecificKeys;

use ILIAS\Filesystem\Stream\Streams;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\Refinery\Factory as Refinery;

class AccessChecker
{
    private const CMDS_WITHOUT_URI_UPDATE = [
        'autosave',
        'checkKey',
        'getOSDNotifications'
    ];

    private ?Data $data = null;

    private bool $object_specific_keys_forced = false;
    private bool $current_user_allowed = true;
    private bool $switch_to_seb_skin_needed = false;
    private bool $key_check_possible_or_unavoidable = false;

    public function isCurrentUserAllowed(): bool
    {
        return $this->current_user_allowed;
    }

    public function isSwitchToSebSkinNeeded(): bool
    {
        return $this->switch_to_seb_skin_needed;
    }

    public function __construct(
        private readonly ?int $ref_id,
        private readonly ?ObjectSpecificKeys $object_specific_keys,
        private readonly KeysChecker $keys_checker,
        private readonly \ilCtrl $ctrl,
        private readonly \ilObjUser $user,
        private readonly \ilAuthSession $auth,
        private readonly \ilAccess $access,
        private readonly \ilRbacReview $rbacreview,
        private readonly Refinery $refinery,
        private readonly HTTPServices $http,
        private readonly Configuration $config
    ) {
        if (!$this->user->getId()
            || $this->user->getId() === ANONYMOUS_USER_ID
            || $this->rbacreview->isAssigned($this->user->getId(), SYSTEM_ROLE_ID)) {
            return;
        }

        $cookie_key = $this->http->wrapper()->cookie()->retrieve(
            'examKey',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always('')
            ])
        );

        $this->data = $this->retrieveSEBData(
            $this->sebKeyInHeaderPostCookieOrNone(),
            $cookie_key
        );

        $this->object_specific_keys_forced = $this->object_specific_keys?->getSebUsageForced() ?? false;
        $this->switch_to_seb_skin_needed = $this->detectSwitchToSEBSkinNeeded();
        $this->current_user_allowed = $this->detectCurrentUserAllowed();
        $this->key_check_possible_or_unavoidable = $this->detectKeyCheckPossibleOrForced();

        $this->updateControlURIInSession();
    }

    public function isKeyCheckPossibleOrUnavoidable(): bool
    {
        return $this->key_check_possible_or_unavoidable;
    }

    public function exitIlias(\ilSEBPlugin $pl): void
    {
        \ilSession::setClosingContext(\ilSession::SESSION_CLOSE_LOGIN);
        if ($this->auth->isValid()) {
            $this->auth->logout();
        }
        session_unset();
        session_destroy();

        $tpl = $pl->getTemplate('default/tpl.seb_forbidden.html');
        $tpl->setCurrentBlock('seb_forbidden_message');
        $tpl->setVariable('SEB_FORBIDDEN_HEADER', $pl->txt('forbidden_header'));
        $tpl->setVariable('SEB_FORBIDDEN_MESSAGE', $pl->txt('forbidden_message'));
        $tpl->setVariable(
            'SEB_LOGIN_LINK',
            'login.php?' . $this->buildTargetString()
            . 'client_id=' . rawurlencode(CLIENT_ID)
            . '&cmd=force_login&lang=' . $this->user->getCurrentLanguage()
        );
        $tpl->setVariable('SEB_LOGIN_LINK_TEXT', $pl->txt('forbidden_login'));
        $tpl->parseCurrentBlock();
        $this->http->saveResponse(
            $this->http->response()
                ->withStatus(403, 'Forbidden')
                ->withBody(Streams::ofString($tpl->get()))
        );
        $this->http->sendResponse();
        exit;
    }

    private function buildTargetString(): string
    {
        if ($this->ref_id) {
            return 'target=' . \ilObject::_lookupType($this->ref_id, true) . '_' . $this->ref_id . '&';
        }

        if ($this->http->wrapper()->query()->has('target')) {
            return 'target='
                . $this->http->wrapper()->query()->retrieve(
                    'target',
                    $this->refinery->kindlyTo()->string()
                ) . '&';
        }

        return '';
    }

    private function sebKeyInHeaderPostCookieOrNone(): DataModes
    {
        if ($this->http->request()->hasHeader(\ilSEBPlugin::REQ_HEADER)) {
            return DataModes::HEADER;
        }
        if ($this->http->wrapper()->cookie()->has('examKey')) {
            return DataModes::COOKIE;
        }
        if ($this->config->isInsecureUserAgentKeyEnabled()
            && $this->http->request()->hasHeader('User-Agent')
            && preg_match('/SEBKEY=(.*)/', $this->http->request()->getHeader('User-Agent')[0])) {
            return DataModes::USER_AGENT;
        }
        return DataModes::NONE;
    }

    private function isInsecureUnhashedMode(): bool
    {
        return $this->data->getMode() === DataModes::USER_AGENT;
    }

    private function detectCurrentUserAllowed(): bool {
        $role_deny = $this->config->getRoleDeny();

        if ($this->object_specific_keys_forced) {
            return $this->access->checkAccess('write', '', $this->ref_id)
                || $this->detectSeb($this->ref_id) === SEBRequestTypes::OBJECT_KEY;
        }

        if ($role_deny === 0
            || $role_deny !== 1 && !$this->rbacreview->isAssigned($this->user->getId(), $role_deny)
            || $this->detectSeb($this->ref_id)->allowsDirectKeyMatch()
            || $this->anySEBKeyIsEnough() && $this->detectSeb()) {
            return true;
        }
        return false;
    }

    private function anySEBKeyIsEnough(): bool
    {
        $cmd = $this->ctrl->getCmd();
        if ($this->config->doesContextAllowAnyObjectKey(\ilContext::getType())
            || $this->config->doesCommandAllowAnyObjectKey($cmd)
            || $this->config->doClassAndCommandAllowAnyObjectKey($this->ctrl->getCmdClass(), $cmd)) {
            return true;
        }

        return $this->config->doesPathAllowAnyObjectKey(
            $this->http->request()->getUri()->getPath()
        );
    }

    private function detectSwitchToSEBSkinNeeded(): bool {
        $role_kiosk = $this->config->getRoleKiosk();

        if (!$this->object_specific_keys_forced
            && ($role_kiosk === 0
                || $role_kiosk !== 1 && !$this->rbacreview->isAssigned($this->user->getId(), $role_kiosk))) {
            return false;
        }
        return true;
    }

    private function detectKeyCheckPossibleOrForced(): bool
    {
        $check_forced = \ilSession::get('check_forced') ?? 0;
        if ($this->data->getMode() === DataModes::COOKIE
            && ($this->data->getExamKey() === '' || $check_forced === 0)) {
            \ilSession::set('check_forced', ++$check_forced);
            return false;
        }
        \ilSession::clear('check_forced');
        return true;
    }

    private function detectSeb(?int $ref_id = null): SEBRequestTypes
    {
        \ilSession::clear('url_to_check');
        $exam_key = $this->data->getExamKey();
        if ($exam_key === '') {
            \ilSession::set('cookie_ui', $this->retrieveFullUri());
            return SEBRequestTypes::NOT_A_SEB_REQUEST;
        }

        $uri = $this->data->getMode() === DataModes::COOKIE
                ? $this->data->getCookieUri()
                : $this->data->getRequestUri();

        if ($this->keys_checker->checkObjectKey(
                $exam_key,
                $uri,
                $ref_id,
                $this->isInsecureUnhashedMode()
            )) {
            return SEBRequestTypes::OBJECT_KEY;
        }
        if ($this->keys_checker->checkGlobalKey(
                $exam_key,
                $uri,
                $this->isInsecureUnhashedMode()
            )) {
            return SEBRequestTypes::GLOBAL_KEY;
        }
        if (!$ref_id && $this->keys_checker->checkKeyAgainstAllObjectKeys(
                $exam_key,
                $uri,
                $this->isInsecureUnhashedMode()
            )) {
            return SEBRequestTypes::OBJECT_KEY_UNSPECIFIC;
        }
        return SEBRequestTypes::INVALID;
    }

    private function retrieveSEBData(DataModes $mode, string $cookie_key): Data
    {
        $data = new Data(
            $mode,
            $this->retrieveFullUri(),
            \ilSession::get('cookie_uri')
        );

        switch ($mode) {
            case DataModes::HEADER:
                return $data->withExamKey(
                    $this->http->request()->getHeader(\ilSEBPlugin::REQ_HEADER)[0]
                );
            case DataModes::COOKIE:
                return $data->withExamKey($cookie_key);
            case DataModes::USER_AGENT:
                preg_match(
                    '/SEBKEY=([a-zA-Z0-9_]+)/',
                    $this->http->request()->getHeader('User-Agent')[0],
                    $matches
                );
                return $data->withExamKey(trim($matches[1]));
            default:
                return $data;
        }
    }

    private function retrieveFullUri(): string
    {
        $uri = $this->http->request()->getUri();
        $protocol = $uri->getScheme();
        $port = $uri->getPort();
        $host = $uri->getHost();
        $path = $uri->getPath();
        $query = $uri->getQuery();

        if ($query !== '') {
            $query = '?' . $query;
        }

        if ($this->config->getIliasRootUri() !== '') {
            $root_uri = new \ILIAS\Data\URI($this->config->getIliasRootUri());
            $protocol = $root_uri->getSchema();
            $port = $root_uri->getPort();
            $host = $root_uri->getHost();
        }

        return $protocol . "://" . $host . $port . $path . $query;
    }

    private function updateControlURIInSession(): void
    {
        if (!in_array($this->ctrl->getCmd(''), self::CMDS_WITHOUT_URI_UPDATE)
            && stristr($this->http->request()->getUri()->getPath(), 'goto.php') === false
            && stristr($this->http->request()->getUri()->getPath(), '/go/') === false) {
            \ilSession::set('cookie_uri', $this->retrieveFullUri());
        }
    }
}
