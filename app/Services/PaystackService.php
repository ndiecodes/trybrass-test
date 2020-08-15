<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use  App\User;
use  App\Bank;
use App\Transaction;

use GuzzleHttp\Client;
use RtLopez\Decimal;







class PaystackService
{

    public static function client(){

         $client = new Client([
            'base_uri' => 'https://api.paystack.co',
            'headers' => ['Authorization' => 'Bearer '.env('PAYSTACK_SECRET_KEY') ]
            ]);

        return $client;
    }


    public static function populateBankDB(Request $request)
    {
        try {
            $res = self::client()->request('GET', '/bank');
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

           return true;

        } catch (\Throwable $th) {
            \Log::debug($th->getMessage());

            throw new \Exception("DB Population Failed");
        }

    }

    public static function getBankAccountName(Request $request)
    {

        try {
            $res = self::client()->request('GET', '/bank/resolve', [
                'query' => [
                        'account_number' => $request->account_number,
                        'bank_code' => $request->bank_code,
                    ]
                ]
            );
            $data = json_decode($res->getBody());

            unset($data->data->bank_id); // remove value frontend doesn't need

            return $data->data;

        } catch (\Throwable $th) {

             \Log::debug($th->getMessage());

           throw new \Exception("Unable to resove Account");
        }

    }

    public static function initiateTransfer(Request $request)
    {

            $authUser = Auth::user();

            $user_bal = Decimal::create($authUser->balance, 2);
            $requested_amount = Decimal::create($request->amount, 2);

            if($user_bal->le($requested_amount))
            {
                throw new \Exception("Insuficient fund!");
            }

            $bank = Bank::where('code', $request->bank_code)->firstOrFail();

            $res = self::client()->request('POST', '/transferrecipient', [
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

            return $transaction;

    }


     public static function completeTransfer($transaction_id)
    {

        try {

            $transaction = Transaction::findOrFail($transaction_id);

            if($transaction->status !== "drafted") {
                //transaction has already been processed
                return $transaction;
            }

            $authUser = Auth::user();

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
            $user_bal = Decimal::create($authUser->balance, 2);
            $trans_amount = Decimal::create($transaction->amount, 2);

            $authUser->balance = $user_bal->sub($trans_amount);
            $authUser->save();

            /*
            *change status to pending
            * we will change this to failed of successfull after we get a cconfirmation from the paystacks webhook
            */
            $transaction->status = "pending";
            $transaction->save();

            DB::commit();

            return $transaction;

        } catch (\Throwable $th) {
            DB::rollBack();

            \Log::debug($th->getMessage());

            throw new \Exception("Unable to Complete Transaction");
        }

    }
}
