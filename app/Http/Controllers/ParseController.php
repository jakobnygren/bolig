<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ParseController extends Controller {

	public function parse(Request $request) {

		$page = $request->pageUrl;
		$minKrm2 = $request->minKrm2;
		$days = $request->daysBack;

		$data = $this->parseSold($page, $minKrm2);

		$this->parseSale(str_replace('solgt/', '', $page))->each(function ($d) use (&$data) {
			$data->push($d);
		});

		return $data
			->filter(function ($d) use ($days) {
				return \Carbon\Carbon::createFromTimestamp(intval($d['date'] / 1000))->gte(\Carbon\Carbon::now()->subDays($days));
				// return \Carbon\Carbon::now()
			})
			->sortBy('date')
			->values()
			->all();

		// return $data2->values()->all();
	}

	private function parseSold($page, $minKrm2) {

		// $page = $request->pageUrl;
		// $minKrm2 = $request->minKrm2;

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
						'sold' => 'Ja',
					]);
				}
			}
			$first = false;
		}

		return $data;

	}

	private function parseSale($page) {

		// $page = $request->pageUrl;

		$str = file_get_contents($page);

		$find = 'table#searchtable tbody tr.pRow';

		$trs = \HTMLDomParser::str_get_html($str)->find($find); //->find('a.bd-view-all-versions'); //->plaintext;

		$data = collect([]);
		// $dataRow = 3;

		// return count($trs);

		for ($i = 0; $i < count($trs); $i++) {

			// if ($i >= 3) {

			$tr = $trs[$i];

			$col0 = trim($tr->find('td', 0)->plaintext);

			$link = 'http://www.boliga.dk/' . $tr->find('td', 0)->find('a', 0)->href;

			$address = $col0;
			$floor = preg_match('/, \d\. t(h|v)/', $col0, $matches);
			$floor = count($matches) > 0 ? preg_replace('/[^\d]+/', '', $matches[0]) : '0';

			$rooms = $tr->find('td', 1)->plaintext;

			$price = str_replace('.', '', $tr->find('td', 2)->plaintext);

			// skip 3 (ejerudgift)

			$m2 = explode(' ', $tr->find('td', 4)->plaintext)[0];

			// skip 5-6 (grund/år)

			$krm2 = str_replace('.', '', $tr->find('td', 7)->plaintext);

			// skip 8 (postnummer)

			$days = intval($tr->find('td', 9)->plaintext);

			$d = \Carbon\Carbon::now()->subDays($days);
			$dateStr = $d->toDateString();

			$tmp = explode('-', $dateStr);
			$dateStr = $tmp[2] . '-' . $tmp[1] . '-' . $tmp[0];

			$date = $d->timestamp * 1000;

			$data->push([
				'address' => $address,
				'floor' => intval($floor),
				'rooms' => intval($rooms),
				'price' => intval($price),
				'm2' => intval($m2),
				'krm2' => intval($krm2),
				'days' => intval($days),
				'date' => intval($date),
				'dateStr' => $dateStr,
				'selected' => true,
				'sold' => 'Nej',
				'link' => $link,
			]);

			// }
		}

		return $data;

		// $data = $data->filter(function ($d) {
		// 	return true; //return \Carbon\Carbon::createFromTimestamp(intval($d['date'] / 1000)) > '2017-02-01';
		// })->sortBy('date');

		// return $data->values()->all();

	}

}
