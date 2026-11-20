<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ConferenceController extends Controller
{
    public function index()
    {
        return view('conferences.index');
    }

    public function create()
    {
        return view('conferences.create');
    }

    public function store(Request $request)
    {
        return redirect()->route('conferences.index')->with('success', 'Conférence créée.');
    }

    public function show($id)
    {
        return view('conferences.show', compact('id'));
    }

    public function edit($id)
    {
        return view('conferences.edit', compact('id'));
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('conferences.index')->with('success', 'Conférence mise à jour.');
    }

    public function destroy($id)
    {
        return redirect()->route('conferences.index')->with('success', 'Conférence supprimée.');
    }
}
