<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;
use  App\User;
use  App\Bank;
use App\Transaction;

use GuzzleHttp\Client;

use Illuminate\Support\Facades\DB;


class TransferController extends Controller
{
    protected $client;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
         $this->middleware('auth');
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

            // return $res->getBody();

            if(!Bank::first())//if there's no bank cache
            {
                foreach ($data->data as $bank) {
                    Bank::create([
                        'name' => $bank->name,
                        'code' => $bank->code,
                        'country' => $bank->country,
                        'currency' => $bank->currency,
                        'type' => $bank->type
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
            'account_number' => 'required|numeric',
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

    public function initiateTransfer(Request $request)
    {
         $this->validate($request, [
            'account_number' => 'required|numeric',
            'account_name' => 'required|string',
            "bank_code" => 'required|string',
            "amount" => 'required|numeric',
            "reason" => 'nullable|string'
        ]);

        try {

            $authUser = Auth::user();

            if(floatval($authUser->balance) < floatval($request->amount) )
            {
                return response()->json(['message' => "Insuficient fund!"], 409);
            }

            $bank = Bank::where('code', $request->bank_code)->firstOrFail();

            $res = $this->client->request('POST', '/transferrecipient', [
                'json' => [
                        'account_number' => $request->account_number,
                        'bank_code' => $request->bank_code,
                        'name' => $request->account_name,
                        'type' => $bank->type,
                        'bank_code' => $bank->code,
                        'currency' =>$bank->currency
                    ]
                ]
            );
            $data = json_decode($res->getBody());

            $transaction = new Transaction;
            $transaction->user_id = $authUser->id;
            $transaction->amount = $request->amount;
            $transaction->bank_id = $bank->id;
            $transaction->reason = $request->reason;
            $transaction->recipient_code = $data->data->recipient_code;
            $transaction->bank_account_number = $request->account_number;
            $transaction->bank_account_name = $request->account_name;

            $transaction->save();

            return response()->json(['data' => $transaction ]);

        } catch (\Throwable $th) {
            \Log::debug($th->getMessage());

            return response()->json(['message' => "Unable to initiate Transfer"], 409);
        }

    }


     public function completeTransfer(Request $request, $transaction_id)
    {

        try {


            $transaction = Transaction::findOrFail($transaction_id);

            if($transaction->status !== "drafted") {
                //transaction has already been processed
                return response()->json(['data' => $transaction  ]);
            }

            $authUser = Auth::user();

            $amount = (floatval($transaction->amount) * 100);

            /*
            * I get this from paystack when I try to complete the transfer
            * So I will be mocking the this trasfer completing by commpleted the API call out
            {
                "status": false,
                "message": "You cannot initiate third party payouts as a starter business"
            }
            */

            // $res = $this->client->request('POST', '/transfer', [
            //     'json' => [
            //             'source' => "balance",
            //             'amount' => $amount ,
            //             'recipient' => $transaction->recipient_code,
            //             'reason' => $transaction->reason,
            //         ]
            //     ]
            // );
            // $data = json_decode($res->getBody());

            DB::beginTransaction();

            //debit amount from users balance
            $authUser->balance = floatval($authUser->balance) - floatval($transaction->amount);
            $authUser->save();

            /*
            *change status to pending
            * we will change this to failed of successfull after we get a cconfirmation from the paystacks webhook
            */
            $transaction->status = "pending";
            $transaction->save();

            DB::commit();

            return response()->json(['data' => $transaction  ]);

        } catch (\Throwable $th) {
            DB::rollBack();

            \Log::debug($th->getMessage());

            return response()->json(['message' => "Unable to Conplete Transfer"], 409);
        }

    }
}
