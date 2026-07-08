<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebController extends Controller
{
    public function home()
    {
        return view('home');
    }

    public function video($id)
    {
        return view('video', ['videoId' => $id]);
    }

    public function admin()
    {
        return view('admin');
    }
}
