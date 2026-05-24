@echo off
echo === Eliminando worktrees prohibidos (CLAUDE.md) ===
echo.

set WORKTREES=elastic-mcnulty-1d0871 eloquent-noether-ef8db1 intelligent-perlman-b399ec

for %%W in (%WORKTREES%) do (
    echo --- %%W ---
    if exist ".claude\worktrees\%%W" (
        rmdir /s /q ".claude\worktrees\%%W"
        echo [OK] Directorio eliminado.
    ) else (
        echo [--] Directorio ya no existe.
    )
    if exist ".git\worktrees\%%W" (
        rmdir /s /q ".git\worktrees\%%W"
        echo [OK] Metadata git eliminada.
    ) else (
        echo [--] Metadata git ya no existe.
    )
    git branch -D claude/%%W 2>nul && echo [OK] Rama eliminada. || echo [--] Rama no existe.
    echo.
)

git worktree prune
echo [OK] git worktree prune ejecutado.

echo.
echo === Estado final ===
git worktree list
git branch -a | findstr /i claude || echo (sin ramas claude/)

echo.
echo === Borrando scripts de diagnostico ===
del /q check_boveda_types.php 2>nul && echo [OK] check_boveda_types.php eliminado. || echo [--] No encontrado.
del /q cleanup_worktree.bat 2>nul && echo [OK] cleanup_worktree.bat eliminado (este archivo).
pause
