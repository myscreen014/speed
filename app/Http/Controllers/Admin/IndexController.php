<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Providers\CoreServiceProvider;


class IndexController extends Controller
{
    public function index() {
    	CoreServiceProvider::start();
    	return view('admin/skeleton')->with('name', 'Victoria');
    }
}
