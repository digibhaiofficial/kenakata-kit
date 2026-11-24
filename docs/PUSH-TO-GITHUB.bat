@echo off
rem Kenakata Kit: one click to send everything to GitHub.
rem Put this file in D:\kenakata-kit\ (next to the kenakata-kit and kenakata-license-panel folders).
rem 1. takes a package from Claude in inbox\<repo>.bundle (if there is one)
rem 2. saves changes made in the folder (by you or an AI agent on this computer)
rem 3. takes newer changes from GitHub
rem 4. sends everything (with version tags) to GitHub
setlocal EnableDelayedExpansion
cd /d "%~dp0"
echo.
echo  Kenakata Kit: sending the code to GitHub ...
echo.
set "GIT="
where git >nul 2>nul && set "GIT=git"
if not defined GIT if exist "%ProgramFiles%\Git\cmd\git.exe" set "GIT=%ProgramFiles%\Git\cmd\git.exe"
if not defined GIT for /d %%D in ("%LOCALAPPDATA%\GitHubDesktop\app-*") do if exist "%%D\resources\app\git\cmd\git.exe" set "GIT=%%D\resources\app\git\cmd\git.exe"
if not defined GIT (echo  Neither Git nor GitHub Desktop was found. Install GitHub Desktop first. & goto fail)
echo  Using: !GIT!
set "ID=-c user.name=DigiBhai -c user.email=digibhai.official@gmail.com"
set GIT_TERMINAL_PROMPT=0
if not exist "%~dp0inbox\done" mkdir "%~dp0inbox\done"
set "PUSHED=1"
call :sync kenakata-kit || goto fail
call :sync kenakata-license-panel || goto fail
if "!PUSHED!"=="1" (
  echo.
  echo  ======================================
  echo   DONE. Everything is on GitHub.
  echo   Tell Claude: "hoyeche"
  echo  ======================================
  echo.
  pause
  exit /b 0
)
echo.
echo  ======================================================
echo   Almost done. GitHub Desktop opens now.
echo   Press the blue "Push origin" button for the repository it shows.
echo   Then tell Claude: "hoyeche"
echo  ======================================================
echo.
start "" "x-github-client://openLocalRepo/%~dp0kenakata-kit"
pause
exit /b 0

:sync
set "R=%~dp0%1"
set "B=%~dp0inbox\%1.bundle"
echo.
echo  - %1
if not exist "%R%\.git" (
  if exist "%B%" (
    "!GIT!" clone -q -b main "%B%" "%R%" || exit /b 1
  ) else (
    "!GIT!" clone -q https://github.com/digibhaiofficial/%1.git "%R%" || exit /b 1
  )
)
"!GIT!" -C "%R%" remote set-url origin https://github.com/digibhaiofficial/%1.git
if exist "%B%" (
  echo    taking the package from inbox ...
  "!GIT!" -C "%R%" fetch -q --tags "%B%" main || exit /b 1
  "!GIT!" %ID% -C "%R%" merge -q --no-edit FETCH_HEAD
  if errorlevel 1 (
    "!GIT!" -C "%R%" merge --abort 2>nul
    echo    The package does not fit the changes in this folder. Send a screenshot to Claude.
    exit /b 1
  )
  move /y "%B%" "%~dp0inbox\done\%1.bundle" >nul
)
"!GIT!" -C "%R%" add -A
"!GIT!" -C "%R%" diff --cached --quiet
if errorlevel 1 (
  echo    saving the changes made in this folder ...
  "!GIT!" %ID% -C "%R%" commit -q -m "Changes made on this computer"
)
"!GIT!" %ID% -C "%R%" pull -q --no-rebase --no-edit origin main
if errorlevel 1 (
  "!GIT!" -C "%R%" merge --abort 2>nul
  echo    GitHub has changes that do not fit. Send a screenshot to Claude.
  exit /b 1
)
for /f "delims=" %%T in ('""!GIT!" -C "%R%" describe --tags --abbrev^=0 2^>nul"') do echo    newest version: %%T
"!GIT!" -C "%R%" push -u origin main --tags
if errorlevel 1 (
  echo    The direct push did not work ^(see the message above^). GitHub Desktop will open.
  set "PUSHED=0"
)
exit /b 0

:fail
echo.
echo  Something went wrong. Take a screenshot of this window and send it to Claude.
echo.
pause
exit /b 1
