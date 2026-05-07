@echo off
REM Export all MySQL databases (non-system)
echo Starting export...

for /f %%i in ('mysql -u root -proot -e "SHOW DATABASES;" -s --skip-column-names') do (
    if not "%%i"=="information_schema" if not "%%i"=="mysql" if not "%%i"=="performance_schema" (
        echo Exporting database %%i...
        mysqldump -u root -proot %%i > "%%i.sql"
        echo Database %%i exported!
    )
)

echo All databases exported!
pause
