/**
This file is part of the SEB-Plugin for ILIAS.

SEB-Plugin for ILIAS is free software: you can redistribute
it and/or modify it under the terms of the GNU General Public License
as published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

SEB-Plugin for ILIAS is distributed in the hope that
it will be useful, but WITHOUT ANY WARRANTY; without even the implied
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with SEB-Plugin for ILIAS.  If not,
see http://www.gnu.org/licenses/.

The SEB-Plugin for ILIAS is a refactoring of a previous Plugin by Stefan
Schneider that can be found on Github
https://github.com/hrz-unimr/Ilias.SEBPlugin
*/
!function(e,t){"use strict";function n(e,t){t.defaultView.addEventListener("beforeunload",(()=>function(e){const t=e;"undefined"!=typeof SafeExamBrowser&&void 0!==SafeExamBrowser.security&&""!==SafeExamBrowser.security.browserExamKey&&(t.cookie="examKey=;expires=-1",t.cookie="configKey=;expires=-1",t.cookie="sebClientVersion=;expires=-1")}(t))),function(e,t){fetch(e,{credentials:"same-origin"}).then((e=>{403!==e.status||e.text().then((e=>{t.open("text/html"),t.write(e),t.close()}))}))}(e,t)}function i(e){e.innerHTML=`${(new Date).toLocaleTimeString([document.documentElement.lang,"en"],{hour:"2-digit",minute:"2-digit"})}`}t.seb=t.seb||{},t.seb.saveAndCheckSEBKey=t=>n(t,e),t.seb.sebClockInit=t=>function(e,t){i(e),t((()=>{i(e)}),500)}(t,e.defaultView.setInterval)}(document,il);
