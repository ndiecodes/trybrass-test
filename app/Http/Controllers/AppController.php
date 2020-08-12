<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;
use  App\User;
use  App\Bank;

use GuzzleHttp\Client;


class AppController extends Controller
{
    protected $client;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.paystack.co',
            'headers' => ['Authorization' => 'Bearer '.env('PAYSTACK_SECRET_KEY') ]
            ]);
    }


    public function getBanks(Request $request)
    {
        try {
            $res = $this->client->request('GET', '/bank');
            $data = json_decode($res->getBody());

            if(!Bank::first())//if there's no bank cache
            {
                foreach ($data->data as $val) {
                    Bank::create([
                        'name' => $val->name,
                        'code' => $val->code
                    ]);
                }
            }

            return response()->json(['data' => Bank::all()]);

        } catch (\Throwable $th) {

            return response()->json(['message' => "Error Retrieving Banks"], 409 );
        }

    }

    public function getBankAccountName(Request $request)
    {
        $this->validate($request, [
            'account_number' => 'required|string',
            "bank_code" => 'required|string'
        ]);

        try {
            $res = $this->client->request('GET', '/bank/resolve', [
                'query' => [
                        'account_number' => $request->account_number,
                        'bank_code' => $request->bank_code,
                    ]
                ]
            );
            $data = json_decode($res->getBody());

            unset($data->data->bank_id); // remove value frontend doesn't need

            return response()->json(['data' => $data->data ]);

        } catch (\Throwable $th) {

            return response()->json(['message' => "Unable to get Account Name"], 409);
        }

    }
}
