<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Core\ComplexController;

/**
 * Alias del controlador de conjuntos.
 *
 * La superficie de conjuntos tiene nombre propio (spec S90): vive en
 * `/api/v1/complexes` y su controlador es
 * `App\Http\Controllers\Api\Core\ComplexController`. Esta clase se conserva
 * como alias para no romper a quien la referencia.
 */
class ResidentialComplexController extends ComplexController {}
