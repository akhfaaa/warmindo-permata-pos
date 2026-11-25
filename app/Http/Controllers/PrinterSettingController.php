<?php

namespace App\Http\Controllers;

class PrinterSettingController extends Controller
{
    public function index()
    {
        return view('settings.printer');
    }
}