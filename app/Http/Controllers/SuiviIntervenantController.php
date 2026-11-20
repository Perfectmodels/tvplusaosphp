<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SuiviIntervenantController extends Controller
{
    public function index()
    {
        return view('suivi_intervenants');
    }
}
