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

use kergomard\SEB\Access\AccessChecker;
use kergomard\SEB\Access\KeysChecker;
use kergomard\SEB\Config\Configuration;
use kergomard\SEB\Config\Repository;
use kergomard\SEB\Presentation\SEBModificationProvider;

use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;
use ILIAS\Refinery\Factory as Refinery;

class ilSEBPlugin extends ilUserInterfaceHookPlugin
{
    public const REQ_HEADER = 'X-Safeexambrowser-Requesthash';
    public const STANDARD_BASE_CLASS = 'ilUIPluginRouterGUI';
    public const SEB_CHECK_KEY_GUI_DEFINITION = [self::STANDARD_BASE_CLASS, ilSEBCheckKeyGUI::class];
    public const CHECK_KEY_COMMAND = 'checkKey';

    private static $forbidden = false;
    private static $kioskmode_checked = false;

    private bool $enable_seb_skin = false;
    private Repository $configuration_repository;
    private Configuration $configuration;

    private ?int $current_ref_id = null;

    public function __construct(
        \ilDBInterface $db,
        \ilComponentRepositoryWrite $component_repository,
        string $id
    ) {
        parent::__construct($db, $component_repository, $id);
        /*
         * We don't want this to be executed on the commandline, as it makes the setup fail
         */
        if (php_sapi_name() === 'cli') {
            return;
        }

        /** @var ILIAS\DI\Container $DIC */
        global $DIC;
        $ctrl = $DIC['ilCtrl'];

        $user = $DIC['ilUser'];
        $auth = $DIC['ilAuthSession'];
        $rbacreview = $DIC['rbacreview'];
        $refinery = $DIC['refinery'];
        $http = $DIC['http'];
        $database = $DIC['ilDB'];
        $layout_meta = $DIC->globalScreen()->layout()->meta();

        /*
         * This is ugly, but we need this to avoid an endless loop when redirecting to the "Forbidden"-Page
         * See the Comment below for the one and only place this MUST be set.
         */
        if (self::$forbidden) {
            return;
        }

        $this->current_ref_id = $this->retrieveRefIdFromQuery(
            $http->wrapper()->query(),
            $refinery
        );
        $this->configuration_repository = new Repository($database, $http);
        $this->configuration = $this->configuration_repository->getGlobalConfiguration();

        $access_checker = new AccessChecker(
            $this->current_ref_id,
            new KeysChecker($this->configuration, $this->configuration_repository),
            $ctrl,
            $user,
            $auth,
            $rbacreview,
            $refinery,
            $http,
            $this->configuration
        );

        if ($access_checker->isKeyCheckPossibleOrUnavoidable() && !$access_checker->isCurrentUserAllowed()) {
            /*
             * This is ugly, but we need this to avoid an endless loop when redirecting to the "Forbidden"-Page
             * This is the one and only place this MUST be set.
             */
            self::$forbidden = true;
            $access_checker->exitIlias($this);
        }

        $obj_type = null;
        if ($this->current_ref_id  !== null) {
            $obj_type = ilObject::_lookupType($this->current_ref_id, true);
        }

        /*
         * We need to switch the kioskmode off in tests to avoid collitions in certain modification providers
         * for the GlobalScreen. We need to check this here, because there simply is no other place.
         */
        if (!self::$kioskmode_checked
            && $access_checker->isSwitchToSebSkinNeeded()
            && $obj_type === 'tst'
        ) {
            $this->disableKioskMode();
        }

        $layout_meta->addJs($this->getDirectory() . '/resources/js/dist/seb.js', true);
        $ctrl->setParameterByClass(ilUIPluginRouterGUI::class, 'ref_id', $this->current_ref_id);

        $this->provider_collection->setModificationProvider(
            new SEBModificationProvider($DIC, $this)
        );

        if ($access_checker->isSwitchToSebSkinNeeded()
            && (self::$kioskmode_checked
                || $this->current_ref_id === null
                || $obj_type !== 'tst')) {
            $this->enable_seb_skin = true;
        }

        if (!$user->getId()
            || $user->getId() === ANONYMOUS_USER_ID
            || $rbacreview->isAssigned($user->getId(), SYSTEM_ROLE_ID)
            || !$access_checker->isCurrentUserAllowed()) {
            $ctrl->setParameterByClass(ilUIPluginRouterGUI::class, 'ref_id', $this->current_ref_id);
            $layout_meta->addOnloadCode(
                "il.seb.saveAndCheckSEBKey('{$ctrl->getLinkTargetByClass(self::SEB_CHECK_KEY_GUI_DEFINITION, self::CHECK_KEY_COMMAND)}');"
            );
        }
    }

    public function getPluginName(): string
    {
        return 'SEB';
    }

    public function getConfigurationRepository(): Repository
    {
        return $this->configuration_repository;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function reloadConfiguration(): void
    {
        $this->configuration = $this->configuration_repository->getGlobalConfiguration();
    }

    public function getCurrentRefId(): ?int
    {
        return $this->current_ref_id;
    }

    public function isShowParticipantPicture(): bool
    {
        return $this->configuration->getShowPaxPic();
    }

    public function isShowParticipantMatriculation(): bool
    {
        return $this->configuration->getShowPaxMatriculation();
    }

    public function isShowParticipantUsername(): bool
    {
        return $this->configuration->getShowPaxUsername();
    }

    public function getHeaderBackgroundColor(): string
    {
        return $this->configuration->getHeaderBackgroundColor();
    }

    public function getHeaderColor(): string
    {
        return $this->configuration->getHeaderColor();
    }

    public function getEnableSEBSkin(): bool
    {
        return $this->enable_seb_skin;
    }

    public function handleEvent(
        string $a_component,
        string $a_event,
        array $a_parameter
    ): void {
        if ($a_event === 'afterLogin') {
            ilSession::clear('last_uri');
        }
    }

    private function disableKioskMode(): void
    {
        $test = new ilObjTest($this->current_ref_id);
        if ($test->getKioskMode() === true) {
            $test->getMainSettingsRepository()->store(
                $test->getMainSettings()->withTestBehaviourSettings(
                    $test->getMainSettings()->getTestBehaviourSettings()->withKioskMode(0)
                )
            );
        }

        self::$kioskmode_checked = true;
    }

    private function retrieveRefIdFromQuery(
        ArrayBasedRequestWrapper $query,
        Refinery $refinery
    ): ?int {
        $ref_id = $query->retrieve(
            'ref_id',
            $refinery->byTrying([
                $refinery->kindlyTo()->int('ref_id'),
                $refinery->always(0)
            ])
        );
        if ($ref_id > 0) {
            return $ref_id;
        }

        if ($query->has('target')) {
            return $this->extractRefIdFromTargetParameter(
                $query->retrieve(
                    'target',
                    $refinery->byTrying([
                        $refinery->custom()->transformation(
                           fn (string $v): ?int => $this->extractRefIdFromTargetParameter($v)
                        ),
                        $refinery->always(null)
                    ])
                )
            );
        }

        return null;
    }

    private function extractRefIdFromTargetParameter(int|string|null $target): ?int
    {
        if ($target === null || is_int($target)) {
            return $target;
        }
        $target_array = explode('_', $target);
        if (is_numeric($target_array[1]) && $target_array[1] > 0) {
            return (int) $target_array[1];
        }

        return null;
    }
}
