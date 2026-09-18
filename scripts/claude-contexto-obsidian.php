<?php

/**
 * Hook SessionStart de Claude Code: vuelca al contexto las notas de entrada de
 * la bóveda de Obsidian para que cada sesión arranque sabiendo qué hay
 * construido, qué falta y cómo se trabaja. La ruta de la bóveda vive en
 * CLAUDE.md y aquí; si se mueve, cambiar ambos.
 *
 * Es PHP y no un `cat` porque el hook se ejecuta con el shell que tenga la
 * máquina (bash o PowerShell) y la ruta lleva espacios: con PHP el comando es
 * el mismo en los dos y no hay que pelearse con las comillas.
 *
 * Si la bóveda no está montada (otra máquina, Drive sin sincronizar) avisa por
 * stdout y sale con 0: un hook que falla bloquea el arranque y eso es peor que
 * arrancar sin documentación.
 */

$boveda = 'G:/My Drive/Emprendimiento/RankPro/Documentos/Documentacion - Obsidian/RankPro Solutions';

$notas = [
    '00 Inicio.md',
    '01 Estado del proyecto.md',
];

if (! is_dir($boveda)) {
    echo "[Obsidian] La bóveda de documentación no está disponible en {$boveda}. ";
    echo "Trabaja con el código y avisa al usuario de que no se cargó la documentación.\n";
    exit(0);
}

echo "# Documentación de RankPro (bóveda Obsidian)\n";
echo "Ruta: {$boveda}\n";
echo "Antes de tocar un módulo lee su nota; al terminar actualiza la nota, '01 Estado del proyecto.md' y '03 Bitácora.md'. Reglas completas en CLAUDE.md.\n\n";

foreach ($notas as $nota) {
    $ruta = "{$boveda}/{$nota}";

    if (! is_file($ruta)) {
        echo "[Obsidian] Falta la nota {$nota}.\n\n";
        continue;
    }

    echo "---- {$nota} ----\n";
    echo file_get_contents($ruta), "\n\n";
}
