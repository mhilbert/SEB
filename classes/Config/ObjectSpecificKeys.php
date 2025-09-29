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

class ObjectSpecificKeys
{
    public function __construct(
        private readonly int $ref_id,
        private readonly bool $force_seb_usage,
        private readonly array $keys_windows,
        private readonly array $keys_macos
    ) {
    }

    public function getMergedKeysArray(): array
    {
        return array_merge($this->keys_windows, $this->keys_macos);
    }

    public function toForm(
        UIFactory $ui_factory,
        Refinery $refinery,
        \ilSEBPlugin $plugin
    ): array {
        $ff = $ui_factory->input()->field();

        $inputs = [
            'keys_windows' => $ff->text(
                $plugin->txt('key_windows'),
                $plugin->txt('key_windows_info')
            )->withMaxLength(Configuration::MAX_CONFIG_VALUE_LENGTH)
            ->withValue(implode(', ', $this->keys_windows)),
            'keys_macos' => $ff->text(
                $plugin->txt('key_macos'),
                $plugin->txt('key_macos_info')
            )->withMaxLength(Configuration::MAX_CONFIG_VALUE_LENGTH)
            ->withValue(implode(', ', $this->keys_macos)),
        ];

        if ($plugin->getConfiguration()->getObjectKeysEnabled()) {
            $inputs['force_seb_usage'] = $ff->checkbox(
                $plugin->txt('force_seb_usage'),
                $plugin->txt('force_seb_usage_info'),
            )->withValue($this->force_seb_usage);
        }

        return [
            'container' => $ff->section(
                $inputs,
                $plugin->txt('title_settings_form'),
                $plugin->txt('description_settings_form')
            )->withAdditionalTransformation(
                $this->buildObjectTransformation($refinery)
            )
        ];
    }

    public function getRefId(): int
    {
        return $this->ref_id;
    }

    public function getSebUsageForced(): bool
    {
        return $this->force_seb_usage;
    }

    public function toStorage(): array
    {
        return [
            'seb_key_win' => [\ilDBConstants::T_TEXT, implode(',', $this->keys_windows)],
            'seb_key_macos' => [\ilDBConstants::T_TEXT, implode(',', $this->keys_macos)],
            'force_seb_usage' => [\ilDBConstants::T_INTEGER, $this->force_seb_usage ? 1 : 0]
        ];
    }

    private function buildObjectTransformation(Refinery $refinery): Transformation
    {
        return $refinery->custom()->transformation(
            fn (array $vs): self => new self(
                $this->ref_id,
                $vs['force_seb_usage'] ?? false,
                $this->buildSEBKeysFromConfigString($vs['keys_windows']),
                $this->buildSEBKeysFromConfigString($vs['keys_macos'])
            )
        );
    }

    private function buildSEBKeysFromConfigString(string $value): array
    {
        return array_map(
            fn (string $v): string => trim($v),
            explode(',', $value)
        );
    }
}
