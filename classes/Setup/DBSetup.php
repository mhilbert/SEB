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

namespace kergomard\SEB\Setup;

class DBSetup implements \ilDatabaseUpdateSteps
{
    protected \ilDBInterface $db;

    public function prepare(\ilDBInterface $db): void
    {
        $this->db = $db;
    }

    public function step_1(): void
    {
        if (!$this->db->tableExists('ui_uihk_seb_conf')) {
            $this->db->createTable(
                'ui_uihk_seb_conf',
                [
                    'name' => [
                        'type' => 'text',
                        'length' => '2000',
                        'notnull' => true
                    ],
                    'value' => [
                        'type' => 'text',
                        'length' => '2000',
                        'notnull' => true
                    ]
                ],
                true,
                false
            );
            $this->db->manipulate(
                'INSERT INTO ui_uihk_seb_conf (name, value) VALUES '
                . '("role_deny", 1),'
                . '("role_kiosk", 1),'
                . '("seb_keys", ""),'
                . '("allow_object_keys", 0),'
                . '("activate_session_control", 0),'
                . '("show_pax_pic", 0),'
                . '("show_pax_matriculation", 0),'
                . '("show_pax_username", 0),'
                . '("ilias_root_uri", "");'
            );
        }
        if (!$this->db->tableExists('ui_uihk_seb_keys')) {
            $this->db->createTable(
                'ui_uihk_seb_keys',
                [
                    'ref_id' => [
                        'type' => 'integer',
                        'length' => 8,
                        'notnull' => true
                    ],
                    'seb_key_win' => [
                        'type' => 'text',
                        'length' => '2000',
                        'notnull' => false
                    ],
                    'seb_key_macos' => [
                        'type' => 'text',
                        'length' => '2000',
                        'notnull' => false
                    ]
                ]
            );
        }
        if (!$this->db->primaryExistsByFields('ui_uihk_seb_keys', ['ref_id'])) {
            $this->db->addPrimaryKey('ui_uihk_seb_keys', ['ref_id']);
        }
        if (!$this->db->primaryExistsByFields('ui_uihk_seb_conf', ['name'])) {
            $this->db->modifyTableColumn(
                'ui_uihk_seb_conf',
                'name',
                [
                    'type' => 'text',
                    'length' => '400',
                    'notnull' => true
                ]
            );
            $this->db->addPrimaryKey('ui_uihk_seb_conf', ['name']);
        }
    }

    public function step_2(): void
    {
        if (!$this->db->tableColumnExists('ui_uihk_seb_keys', 'force_seb_usage')) {
            $this->db->addTableColumn(
                'ui_uihk_seb_keys',
                'force_seb_usage',
                [
                    'type' => \ilDBConstants::T_INTEGER,
                    'length' => 1,
                    'notnull' => true,
                    'default' => 0
                ]
            );
        }
    }
}
