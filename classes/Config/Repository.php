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

use ILIAS\HTTP\Services as HTTPServices;

class Repository
{
    private const CONFIG_TABLE_NAME = 'ui_uihk_seb_conf';
    private const OBJECT_KEYS_TABLE_NAME = 'ui_uihk_seb_keys';

    public function __construct(
        private readonly \ilDBInterface $db,
        private readonly HTTPServices $http
    ) {
    }

    public function getGlobalConfiguration(): Configuration
    {
        $query = $this->db->query('SELECT * FROM ' . self::CONFIG_TABLE_NAME);
        $config = [];
        while (($row = $this->db->fetchAssoc($query)) !== null) {
            $config[$row['name']] = $this->buildConfigValueFromDBValue(
                $row['name'],
                $row['value']
            );
        }

        return new Configuration(
            $config['seb_keys'],
            $config['role_deny'],
            $config['role_kiosk'],
            $config['allow_object_keys'],
            $config['activate_session_control'],
            $config['show_pax_pic'],
            $config['show_pax_matriculation'],
            $config['show_pax_username'],
            $config['ilias_root_uri'],
            $config['header_bg_color'] ?? null,
            $config['header_color'] ?? null
        );
    }

    public function saveGlobalConfiguration(Configuration $conf): int
    {
        $r = 0;
        foreach ($conf->toStorage() as $name => $value) {
            if ($this->db->replace(
                self::CONFIG_TABLE_NAME,
                [
                    'name' => [\ilDBConstants::T_TEXT, $name]
                ],
                [
                    'value' => [\ilDBConstants::T_TEXT, $value]
                ]
            ) > 0) {
                $r += 1;
            }
        }
        return $r;
    }

    public function saveObjectKeys(ObjectSpecificKeys $object_keys): int
    {
        return $this->db->replace(
            'ui_uihk_seb_keys',
            [
                'ref_id' => ['integer', $object_keys->getRefId()]
            ],
            $object_keys->toStorage()
        );
    }

    public function getObjectSpecificKeysFor(int $ref_id): ObjectSpecificKeys
    {
        $keys_windows = [];
        $keys_macos = [];
        $force_seb_usage = false;
        if (($keys_config = $this->db->fetchAssoc(
            $this->db->query(
                'SELECT force_seb_usage, seb_key_win, seb_key_macos FROM ui_uihk_seb_keys where ref_id='
                    . $this->db->quote($ref_id, 'integer')
            )
        ))) {
            $keys_windows = $this->buildSEBKeysFromConfigString($keys_config['seb_key_win']);
            $keys_macos = $this->buildSEBKeysFromConfigString($keys_config['seb_key_macos']);
            $force_seb_usage = $keys_config['force_seb_usage'] === 1;
        }
        return new ObjectSpecificKeys(
            $ref_id,
            $force_seb_usage,
            $keys_windows,
            $keys_macos
        );
    }

    public function getAllObjectSpecificKeys(): array
    {
        return array_reduce(
            $this->db->fetchAll(
                $this->db->query('SELECT * FROM ' . self::OBJECT_KEYS_TABLE_NAME)
            ),
            function (array $c, array $vs): array {
                $c[] = new ObjectSpecificKeys(
                    (int) $vs['ref_id'],
                    $this->buildSEBKeysFromConfigString($vs['seb_key_win']),
                    $this->buildSEBKeysFromConfigString($vs['seb_key_macos'])
                );
                return $c;
            },
            []
        );
    }

    private function buildConfigValueFromDBValue(
        string $name,
        string $value
    ): string|int|bool|array {
        switch ($name) {
            case 'seb_keys':
                return $this->buildSEBKeysFromConfigString($value);
            case 'activate_session_control':
            case 'allow_object_keys':
            case 'show_pax_pic':
            case 'show_pax_matriculation':
            case 'show_pax_username':
                return $value === '1';
            case 'role_deny':
            case 'role_kiosk':
                return (int) $value;
            case 'ilias_root_uri':
                if ($value === '') {
                    return $this->buildDefaultRootUri();
                }
                return $value;
            default:
                return $value;
        }
    }

    private function buildSEBKeysFromConfigString(string $value): array
    {
        return array_map(
            fn (string $v): string => trim($v),
            explode(',', $value)
        );
    }

    private function buildDefaultRootUri(): string
    {
        $uri = $this->http->request()->getUri();
        return $uri->getScheme() . '://' . $uri->getHost() . $uri->getPort();
    }
}
