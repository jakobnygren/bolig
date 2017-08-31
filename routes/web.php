<?php

use Illuminate\Http\Request;

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

Route::get('/', function () {
	return view('welcome');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');

Route::post('/parsedom', function (Request $request) {

	$page = $request->pageUrl;
	$minKrm2 = $request->minKrm2;

	$str = file_get_contents($page);

	$find = 'table#searchresult tbody tr';

	$trs = \HTMLDomParser::str_get_html($str)->find($find); //->find('a.bd-view-all-versions'); //->plaintext;

	$data = collect([]);
	$first = true;
	foreach ($trs as $tr) {

		if (!$first) {

			$col0 = trim($tr->find('td', 0)->plaintext);

			$address = $col0;
			$floor = preg_match('/, \d\. t(h|v)/', $col0, $matches);
			$floor = count($matches) > 0 ? preg_replace('/[^\d]+/', '', $matches[0]) : '0';

			$price = str_replace('.', '', $tr->find('td', 1)->plaintext);

			$col2 = $tr->find('td', 2)->plaintext;

			$isNormalSale = strpos($col2, 'Alm. Salg');
			preg_match('/\d\d-\d\d-\d\d\d\d/', $col2, $matches);
			$dateStr = $matches[0];
			$date = \Carbon\Carbon::parse($dateStr)->timestamp * 1000;

			$krm2 = str_replace('.', '', $tr->find('td', 3)->plaintext);
			$rooms = $tr->find('td', 4)->plaintext;
			$m2 = $tr->find('td', 6)->plaintext;

			// $data->push($price);
			if ($isNormalSale && $krm2 >= $minKrm2) {

				$data->push([
					'address' => $address,
					'date' => intval($date),
					'price' => $price,
					'krm2' => $krm2,
					'rooms' => $rooms,
					'm2' => $m2,
					'dateStr' => $dateStr,
					'floor' => $floor,
					'selected' => true,
				]);
			}
		}

		$first = false;

	}

	$data = $data->filter(function ($d) {
		return true; //return \Carbon\Carbon::createFromTimestamp(intval($d['date'] / 1000)) > '2017-02-01';
	})->sortBy('date');

	return $data->values()->all();

});
