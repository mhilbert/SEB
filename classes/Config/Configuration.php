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

namespace kergomard\SEB\Config;

use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Refinery\Transformation;
use ILIAS\UI\Factory as UIFactory;

class Configuration
{
    /**
     * By setting this to true you can enable a check for a seb-key in the
     * user-agent header. There are good reasons, why this setting can not
     * be changed from the interface: It reduces the security guarantees
     * provided by the SEB and this plugin drastically. If you are sure you
     * know what you are doing, change the value to `true` to enable it.
     */
    private const ENABLE_INSECURE_USER_AGENT_KEY = false;

    public const MAX_CONFIG_VALUE_LENGTH = 2000;
    private const CMDS_WITHOUT_SEB_KEY_TAB = [
        'showquestion',
        'outuserresultsoverview',
        'outuserpassdetails',
        'outparticipantsresultsoverview',
        'outparticipantspassdetails',
        'editquestion',
        'showsolution',
        'showfeedbackform',
        'showanswerstatistic',
        'suggestedsolution',
        'outsolutionexplorer',
        'linkchilds'
    ];
    private const CMD_CLASSES_WITHOUT_SEB_KEY_TAB = [
        'ilsebsessionstabgui',
        'ilsebsettingstabgui',
        'ilobjectactivationgui',
        'ilassquestionpreviewgui',
        'ilassquestionrelatednavigationbargui',
        'iltestpagegui',
        'ilassquestionpagegui',
        'iltestplayerrandomquestionsetgui',
        'iltestplayerfixedquestionsetgui',
        'ilassquestionfeedbackeditinggui',
        'ilassquestionhintstablegui',
        'ilassquestionhintgui',
        'ilconfirmationgui',
        'iltestsubmissionreviewgui',
        'ilparticipantstestresultsgui',
        'ilobjrolegui',
        'ilpcmediaobjectgui'
    ];

     private const REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS = [
        'context_check' => [
            \ilContext::CONTEXT_WAC
        ],
        'path_check' => [
            'logout.php',
            '/src/GlobalScreen/Client/notify.php'
        ],
        'cmd_check' => [
            'getOSDNotifications',
            'removeOSDNotifications',
            'showHelp'
        ],
        'cmd_and_cmdclass_check' => [
            'ilpersonalprofilegui' => [
                'showPersonalData',
                'showPublicProfile',
                'savePersonalData',
                'savePublicProfile'
            ],
            'ilpersonalsettingsgui' => [
                'showPassword'
            ],
            'ilstartupgui' => [
                'getAcceptance',
                'confirmAcceptance'
            ],
            'ilhelpgui' => [
                'showHelp'
            ]
        ]
    ];

    private const VALID_COLOR_REGEXP = '/^(\#[\da-fA-F]{3}|\#[\da-fA-F]{6}|rgba\(((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*,\s*){2}((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*)(,\s*(0\.\d+|1))\)|hsla\(\s*((\d{1,2}|[1-2]\d{2}|3([0-5]\d|60)))\s*,\s*((\d{1,2}|100)\s*%)\s*,\s*((\d{1,2}|100)\s*%)(,\s*(0\.\d+|1))\)|rgb\(((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*,\s*){2}((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*)|hsl\(\s*((\d{1,2}|[1-2]\d{2}|3([0-5]\d|60)))\s*,\s*((\d{1,2}|100)\s*%)\s*,\s*((\d{1,2}|100)\s*%)\))$/';
    private const DEFAULT_HEADER_BG_COLOR = '#6EA03C';
    private const DEFAULT_HEADER_COLOR = '#FFF';

    public function __construct(
        private readonly array $global_keys,
        private readonly int $role_deny,
        private readonly int $role_kiosk,
        private readonly bool $object_keys_enabled,
        private readonly bool $session_control_enabled,
        private readonly bool $show_pax_pic,
        private readonly bool $show_pax_matriculation,
        private readonly bool $show_pax_username,
        private readonly string $ilias_root_uri,
        private ?string $header_background_color,
        private ?string $header_color
    ) {
        if ($this->header_background_color === null) {
            $this->header_background_color = $header_background_color ?? self::DEFAULT_HEADER_BG_COLOR;
        }
        if ($this->header_color === null) {
            $this->header_color = $header_color ?? self::DEFAULT_HEADER_COLOR;
        }
    }

    public function getCmdsWithoutSebKeyTab(): array
    {
        return self::CMDS_WITHOUT_SEB_KEY_TAB;
    }

    public function getCmdClassesWithoutSebKeyTab(): array
    {
        return self::CMD_CLASSES_WITHOUT_SEB_KEY_TAB;
    }

    public function doesContextAllowAnyObjectKey(string $context): bool
    {
        return in_array($context, self::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['context_check']);
    }

    public function doesCommandAllowAnyObjectKey(string $command): bool
    {
        return in_array($command, self::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['cmd_check']);
    }

    public function doesPathAllowAnyObjectKey(string $path): bool
    {
        foreach (self::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['path_check'] as $exempted_path) {
            if (mb_strpos($path, $exempted_path)) {
                return true;
            }
        }
        return false;
    }

    public function doClassAndCommandAllowAnyObjectKey(
        string $command_class,
        string $command
    ): bool {
        return array_key_exists($command_class, self::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['cmd_and_cmdclass_check'])
            && in_array($command, self::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['cmd_and_cmdclass_check'][$command_class]);
    }

    public function getGlobalKeys(): array
    {
        return $this->global_keys;
    }

    public function getRoleDeny(): int
    {
        return $this->role_deny;
    }

    public function getRoleKiosk(): int
    {
        return $this->role_kiosk;
    }

    public function getObjectKeysEnabled(): bool
    {
        return $this->object_keys_enabled;
    }

    public function getSessionControlEnabled(): bool
    {
        return $this->session_control_enabled;
    }

    public function getShowPaxPic(): bool
    {
        return $this->show_pax_pic;
    }

    public function getShowPaxMatriculation(): bool
    {
        return $this->show_pax_matriculation;
    }

    public function getShowPaxUsername(): bool
    {
        return $this->show_pax_username;
    }

    public function getIliasRootUri(): string
    {
        return $this->ilias_root_uri;
    }

    public function getHeaderBackgroundColor(): string
    {
        return $this->header_background_color;
    }

    public function getHeaderColor(): string
    {
        return $this->header_color;
    }

    public function isInsecureUserAgentKeyEnabled(): bool
    {
        return self::ENABLE_INSECURE_USER_AGENT_KEY;
    }

    public function toForm(
        UIFactory $ui_factory,
        Refinery $refinery,
        array $global_roles,
        \ilSEBPlugin $plugin
    ): array {
        $ff = $ui_factory->input()->field();

        $global_roles_options = $this->buildGlobalRolesSelectionArray(
            $plugin,
            $global_roles
        );

        $session_control_enabled = \ilSecuritySettings::_getInstance()
            ->isPreventionOfSimultaneousLoginsEnabled();

        $color_constraint = $refinery->custom()->constraint(
            static fn (string $v): bool => preg_match(self::VALID_COLOR_REGEXP, $v)=== 1,
            $plugin->txt('invalid_color')
        );

        return [
            'configuration' => $ff->section(
                [
                    'object_keys_enabled' => $ff->checkbox(
                        $plugin->txt('allow_object_keys'),
                        $plugin->txt('allow_object_keys_info')
                    )->withValue($this->object_keys_enabled),
                    'global_keys' => $ff->text(
                        $plugin->txt('seb_keys'),
                        $plugin->txt('seb_keys_info')
                    )->withMaxLength(Configuration::MAX_CONFIG_VALUE_LENGTH)
                    ->withValue(implode(', ', $this->global_keys)),
                    'role_deny' => $ff->select(
                        $plugin->txt('role_deny'),
                        $global_roles_options
                    )->withRequired(true)
                    ->withByline($plugin->txt('role_deny_info'))
                    ->withValue($this->role_deny),
                    'role_kiosk' => $ff->select(
                        $plugin->txt('role_kiosk'),
                        $global_roles_options
                    )->withRequired(true)
                    ->withByline($plugin->txt('role_kiosk_info'))
                    ->withValue($this->role_kiosk),
                    'session_control_enabled' => $ff->checkbox(
                        $plugin->txt('activate_session_control'),
                        $session_control_enabled
                            ? $plugin->txt('activate_session_control_info')
                            : $plugin->txt('activate_session_control_info_disabled')
                    )->withValue($this->session_control_enabled)
                    ->withDisabled(!$session_control_enabled),
                    'header_background_color' => $ff->text(
                        $plugin->txt('header_bg_color'),
                        $plugin->txt('header_bg_color_info')
                    )->withMaxLength(Configuration::MAX_CONFIG_VALUE_LENGTH)
                    ->withAdditionalTransformation($color_constraint)
                    ->withValue($this->header_background_color),
                    'header_color' => $ff->text(
                        $plugin->txt('header_color'),
                        $plugin->txt('header_color_info')
                    )->withMaxLength(Configuration::MAX_CONFIG_VALUE_LENGTH)
                    ->withAdditionalTransformation($color_constraint)
                    ->withValue($this->header_color),
                    'show_pax_pic' => $ff->checkbox(
                        $plugin->txt('show_pax_pic'),
                        $plugin->txt('show_pax_pic_info')
                    )->withValue($this->show_pax_pic),
                    'show_pax_matriculation' => $ff->checkbox(
                        $plugin->txt('show_pax_matriculation'),
                        $plugin->txt('show_pax_matriculation_info')
                    )->withValue($this->show_pax_matriculation),
                    'show_pax_username' => $ff->checkbox(
                        $plugin->txt('show_pax_username'),
                        $plugin->txt('show_pax_username_info')
                    )->withValue($this->show_pax_username),
                    'ilias_root_uri' => $ff->text(
                        $plugin->txt('ilias_root_uri'),
                        $plugin->txt('ilias_root_uri_info')
                    )->withMaxLength(Configuration::MAX_CONFIG_VALUE_LENGTH)
                    ->withValue($this->ilias_root_uri)
                ],
                $plugin->txt('config')
            )->withAdditionalTransformation(
                $this->buildConfigTransformation($refinery)
            )
        ];
    }

    public function toStorage(): array
    {
        return [
            'seb_keys' => implode(',', $this->global_keys),
            'allow_object_keys' => $this->object_keys_enabled,
            'role_deny' => $this->role_deny,
            'role_kiosk' => $this->role_kiosk,
            'activate_session_control' => $this->session_control_enabled,
            'show_pax_pic' => $this->show_pax_pic,
            'show_pax_matriculation' => $this->show_pax_matriculation,
            'show_pax_username' => $this->show_pax_username,
            'ilias_root_uri' => $this->ilias_root_uri,
            'header_bg_color' => $this->header_background_color,
            'header_color' => $this->header_color
        ];
    }

    private function buildGlobalRolesSelectionArray(
        \ilSEBPlugin $plugin,
        array $global_roles
    ): array {
        $roles = [
            0 => $plugin->txt('role_none'),
            1 => $plugin->txt('role_all_except_admin')
        ];
        return array_reduce(
            $global_roles,
            static function (array $c, int $v): array {
                $c[$v] = \ilObject::_lookupTitle($v);
                return $c;
            },
            $roles
        );
    }

    private function buildConfigTransformation(
        Refinery $refinery
    ): Transformation {
        return $refinery->custom()->transformation(
            static fn (array $vs): self => new self(
                array_map(
                    fn (string $v): string => trim($v),
                    explode(',', $vs['global_keys'])
                ),
                $refinery->kindlyTo()->int()->transform($vs['role_deny']),
                $refinery->kindlyTo()->int()->transform($vs['role_kiosk']),
                $vs['object_keys_enabled'],
                $vs['session_control_enabled'],
                $vs['show_pax_pic'],
                $vs['show_pax_matriculation'],
                $vs['show_pax_username'],
                $vs['ilias_root_uri'],
                $vs['header_background_color'],
                $vs['header_color']
            )
        );
    }
}
