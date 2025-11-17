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

namespace kergomard\SEB\Presentation;

use ILIAS\GlobalScreen\Identification\PluginIdentificationProvider;
use ILIAS\GlobalScreen\Scope\Layout\Builder\StandardPageBuilder;
use ILIAS\GlobalScreen\Scope\Layout\Factory\BreadCrumbsModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\FooterModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\LayoutModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\LogoModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\MainBarModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\MetaBarModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\TitleModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\PageBuilderModification;
use ILIAS\GlobalScreen\Scope\Layout\Provider\AbstractModificationPluginProvider;
use ILIAS\GlobalScreen\Scope\Layout\Provider\PagePart\PagePartProvider;
use ILIAS\GlobalScreen\ScreenContext\Stack\CalledContexts;
use ILIAS\GlobalScreen\ScreenContext\Stack\ContextCollection;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Component\Breadcrumbs\Breadcrumbs;
use ILIAS\UI\Component\Button\Bulky as BulkyButton;
use ILIAS\UI\Component\Image\Image;
use ILIAS\UI\Component\Layout\Page\Page;
use ILIAS\UI\Component\MainControls\Footer;
use ILIAS\UI\Component\MainControls\MainBar;
use ILIAS\UI\Component\MainControls\MetaBar;
use ILIAS\UI\Component\MainControls\Slate\Combined as CombinedSlate;
use ILIAS\UI\Implementation\Component\MainControls\Slate\Legacy as LegacySlate;
use ILIAS\UI\Component\Listing\Workflow\Workflow;

class SEBModificationProvider extends AbstractModificationPluginProvider
{
    protected PluginIdentificationProvider $if;
    private const SCRIPT = <<<SCRIPT
<script>
    if (typeof SafeExamBrowser !== 'undefined'
        && typeof SafeExamBrowser.security !== 'undefined'
        && SafeExamBrowser.security.browserExamKey !== '') {
        document.cookie = "examKey=" + SafeExamBrowser.security.browserExamKey;
        document.cookie = "configKey=" + SafeExamBrowser.security.configKey;
        document.cookie = "sebClientVersion=" + SafeExamBrowser.version;
    }
</script>
SCRIPT;

    private ?bool $in_running_test = null;

    public function isInterestedInContexts(): ContextCollection
    {
        return $this->context_collection->main();
    }

    public function getPageBuilderDecorator(
        CalledContexts $screen_context_stack
    ): ?PageBuilderModification {
        return $this->factory->page()->withModification(
            function (PagePartProvider $parts): Page {
                $p = new StandardPageBuilder();
                $page = $p->build($parts);
                return $page->withTitle(self::SCRIPT . $parts->getTitle());
            }
        )->withHighPriority();
    }

    public function getMainBarModification(
        CalledContexts $screen_context_stack
    ): ?MainBarModification {
        if (!$this->plugin->getEnableSEBSkin()) {
            return null;
        }

        return $this->dic->globalScreen()->layout()->factory()->mainbar()->withModification(
            function (MainBar $current = null) use ($screen_context_stack): ?MainBar {
                $question_list_enabled = $screen_context_stack
                    ->current()
                    ->getAdditionalData()
                    ->exists(\ilTestPlayerLayoutProvider::TEST_PLAYER_QUESTIONLIST);
                $this->addCSS($question_list_enabled);
                if ($this->isTestRunning()) {
                    $this->dic->globalScreen()->layout()->meta()->addOnloadCode(
                        'il.seb.sebClockInit(document.querySelector("#kioskClock"))'
                    );
                }

                if ($current === null
                    || !$question_list_enabled) {
                    return $current?->withClearedEntries();
                }

                $ui_factory = $this->dic->ui()->factory();
                $lng = $this->dic['lng'];

                return $current
                    ->withClearedEntries()
                    ->withToolsButton(
                        $ui_factory->button()->bulky(
                            $ui_factory->symbol()->icon()->standard('tst', $lng->txt('more')),
                            $lng->txt('tools'),
                            ''
                        )->withEngagedState(true)
                    )->withAdditionalToolEntry(
                        'questionlist',
                        $this->buildQuestionSlate(
                            $ui_factory,
                            $lng->txt('mainbar_button_label_questionlist'),
                            $screen_context_stack->current()->getAdditionalData()->get(
                                \ilTestPlayerLayoutProvider::TEST_PLAYER_QUESTIONLIST
                            )
                        )
                    );
            }
        )->withPriority(LayoutModification::PRIORITY_HIGH - 1);
    }

    public function getMetaBarModification(
        CalledContexts $screen_context_stack
    ): ?MetaBarModification {
        if (!$this->plugin->getEnableSEBSkin()) {
            return null;
        }
        return $this->dic->globalScreen()->layout()->factory()->metabar()->withModification(
            function (MetaBar $current = null): ?MetaBar {
                $empty_metabar = $current->withClearedEntries();
                if (!$this->isTestRunning()) {
                    $empty_metabar = $this->withLanguageAndLogout($empty_metabar);
                }
                return $empty_metabar;
            }
        )->withPriority(LayoutModification::PRIORITY_HIGH - 1);
    }

    public function getLogoModification(
        CalledContexts $screen_context_stack
    ): ?LogoModification {
        if (!$this->plugin->getEnableSEBSkin()) {
            return null;
        }
        return $this->dic->globalScreen()->layout()->factory()->logo()->withModification(
            function (Image $current = null): ?Image {
                $logo_path = './Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/SEB/templates/images/HeaderIcon.png';
                $logo_alt = 'SEB Logo';
                if ($this->plugin->isShowParticipantPicture()) {
                    $logo_path = $this->dic->user()->getPersonalPicturePath('small', true);
                    $logo_alt = $this->dic->user()->getFullname();
                }
                return $this->withLogoAction(
                    $this->dic->ui()->factory()->image()->standard($logo_path, $logo_alt)
                );
            }
        )->withHighPriority();
    }

    public function getResponsiveLogoModification(
        CalledContexts $screen_context_stack
    ): ?LogoModification {
        if (!$this->plugin->getEnableSEBSkin()) {
            return null;
        }
        return $this->getLogoModification($screen_context_stack);
    }

    public function getTitleModification(
        CalledContexts $screen_context_stack
    ): ?TitleModification {
        if (!$this->plugin->getEnableSEBSkin()) {
            return null;
        }
        return $this->dic->globalScreen()->layout()->factory()->title()->withModification(
            fn (string $current = null): string => $this->initializeHeaderBuilder()
                ->getParsedTitleString()
        )->withHighPriority();
    }

    public function getBreadCrumbsModification(
        CalledContexts $screen_context_stack
    ): ?BreadCrumbsModification {
        if (!$this->plugin->getEnableSEBSkin()) {
            return null;
        }
        return $this->dic->globalScreen()->layout()->factory()->breadcrumbs()->withModification(
            static fn (Breadcrumbs $current = null): ?Breadcrumbs => null
        )->withHighPriority();
    }

    public function getFooterModification(
        CalledContexts $screen_context_stack
    ): ?FooterModification {
        if (!$this->plugin->getEnableSEBSkin()) {
            return null;
        }
        return $this->dic->globalScreen()->layout()->factory()->footer()->withModification(
            static fn (Footer $current = null): ?Footer => null
        )->withHighPriority();
    }

    private function initializeHeaderBuilder(): HeaderBuilder
    {
        $title_object = new HeaderBuilder(
            $this->plugin,
            $this->dic->user(),
            $this->dic->database(),
            $this->dic->settings()->get('short_inst_name', '')
        );
        if ($this->plugin->getCurrentRefId() === null
            || $this->plugin->getCurrentRefId() === 0) {
            return $title_object;
        }

        return $title_object->withObject(
            \ilObjectFactory::getInstanceByRefId($this->plugin->getCurrentRefId())
        );
    }

    private function isTestRunning(): bool
    {
        if ($this->in_running_test === null) {
            $this->in_running_test = $this->determineRunningTest();
        }

        return $this->in_running_test;
    }

    private function determineRunningTest(): bool
    {
        if ($this->dic->ctrl()->getContextObjType() !== 'tst') {
            return false;
        }

        $test_session = new \ilTestSession(
            $this->dic->database(),
            $this->dic->user()
        );
        $test_session->loadTestSession(
            (new \ilObjTest($this->plugin->getCurrentRefId()))->getTestId(),
            $this->dic->user()->getId()
        );

        if ($test_session->getActiveId() === 0
            || (string) $test_session->getLastStartedPass() === (string ) $test_session->getLastFinishedPass()) {
            return false;
        }

        return true;
    }

    private function withLanguageAndLogout(MetaBar $meta_bar): MetaBar
    {
        $f = $this->dic->ui()->factory();

        $user = $this->dic->user();
        if ($user->getCurrentLanguage() !== null
            && $user->getLanguage() !== $user->getCurrentLanguage()) {
            $user->setLanguage($user->getCurrentLanguage());
            $user->update();
        }

        $languages = $this->getEntriesForAvailableLanguages();
        if (count($languages) > 1) {
            $meta_bar = $this->addLanguagesToMetaBar($meta_bar, $languages);
        }

        return $meta_bar->withAdditionalEntry(
            'logout',
            $f->button()->bulky(
                $f->symbol()->glyph()->logout(),
                $this->dic->language()->txt('logout'),
                \ilStartUpGUI::logoutUrl()
            )
        );
    }

    private function addLanguagesToMetaBar(
        MetaBar $meta_bar,
        array $languages
    ): MetaBar {
        $f = $this->dic->ui()->factory();
        return $meta_bar->withAdditionalEntry(
            'lang_menu',
            array_reduce(
                $languages,
                fn (CombinedSlate $c, BulkyButton $v): CombinedSlate => $c
                    ->withAdditionalEntry($v),
                $f->mainControls()->slate()->combined(
                    'language',
                    $f->symbol()->glyph()->language()
                )
            )
        );
    }

    /**
     * @return \ILIAS\UI\Component\Button\Bulky[]
     **/
    private function getEntriesForAvailableLanguages(): array
    {
        $f = $this->dic->ui()->factory();
        $base = $this->getBaseURL();

        return array_map(
            function (string $v) use ($f, $base): BulkyButton {
                $lang_name = $this->dic->language()->_lookupEntry(
                    $v,
                    'meta',
                    "meta_l_{$v}"
                );
                return $f->button()->bulky(
                    $f->symbol()->icon()->standard(
                        'none',
                        $lang_name
                    )->withAbbreviation($v),
                    $lang_name,
                    $this->appendUrlParameterString($base, "lang={$v}")
                );
            },
            $this->dic->language()->getInstalledLanguages()
        );
    }

    private function withLogoAction(Image $image): Image
    {
        if ($this->isTestRunning()) {
            return $image;
        }

        $url = \ilUserUtil::getStartingPointAsUrl();
        if ($url === '') {
            $url = './goto.php?target=root_1';
        }

        return $image->withAction($url);
    }

    private function appendUrlParameterString(
        string $existing_url,
        string $addition
    ): string {
        return str_replace(
            '?&',
            '?',
            strpos($existing_url, '?') !== false
                ? $existing_url . "&" . $addition
                : $existing_url . "?" . $addition
        );
    }

    private function getBaseURL(): string
    {
        $uri = $this->dic->http()->request()->getUri();
        return $uri->withQuery(
            preg_replace(
                '/&*lang=[a-z]{2}&*/',
                '',
                $uri->getQuery()
            )
        )->__toString();
    }

    private function addCSS(bool $has_question_list): void
    {
        $this->dic->ui()->mainTemplate()->addCss($this->plugin->getStyleSheetLocation('default/seb.css'));

        if ($this->plugin->isShowParticipantPicture()) {
            $this->dic->ui()->mainTemplate()->addCss($this->plugin->getStyleSheetLocation('default/seb_with_profile_picture.css'));
        }

        if (!$has_question_list) {
            $this->dic->ui()->mainTemplate()->addCss($this->plugin->getStyleSheetLocation('default/seb_hide_main_bar.css'));
        }

        if ($this->isTestRunning()) {
            $this->dic->ui()->mainTemplate()->addCss($this->plugin->getStyleSheetLocation('default/seb_test_running.css'));
        }
    }

    private function buildQuestionSlate(
        UIFactory $ui_factory,
        string $label,
        Workflow $question_list
    ): LegacySlate {
        return $ui_factory->maincontrols()->slate()->legacy(
            $label,
            $ui_factory->symbol()->icon()->standard('tst', $label),
            $ui_factory->legacy(
                $this->dic->ui()->renderer()->render(
                    $question_list
                )
            )
        );
    }
}
