<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

class A {

}
$a = new A();

/* FRONT */
Route::get('/', function () {
    return view('welcome');
});

/* ADMIN */
Auth::routes();
Route::group(
	[
		'prefix' => 'admin',
		'middleware'=> ['auth']
	], 
	function () {
		Route::get('/', function() {
			return redirect()->action('Admin\IndexController@index');
		});
		Route::get('/index', 'Admin\IndexController@index');
	}
);

