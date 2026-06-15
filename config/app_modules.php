<?php
/**
 * Visibilidad de módulos en la interfaz.
 *
 * El código legacy de ventas permanece en modules/ pero queda deshabilitado
 * según el alcance de la tesis: Compras y Producción.
 *
 * @see Lucas_Medina_analisis.docx — Sistema de Gestión de Compras y Producción
 */

/** Módulo de ventas (pedidos, facturas, cobranzas, caja, libro ventas, etc.) */
define('UI_MODULO_VENTAS', false);

/** Módulo de producción (menú visible; rutas se irán habilitando al implementar cada UC) */
define('UI_MODULO_PRODUCCION', true);
