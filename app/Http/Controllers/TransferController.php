<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use  App\Bank;


use App\Services\PaystackService;


class TransferController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
         $this->middleware('auth');

    }


    public function getBanks(Request $request)
    {

        if(PaystackService::populateBankDB($request)) {

            return response()->json(['data' => Bank::all()]);

        }else  {

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

            $result  = PaystackService::getBankAccountName($request);

            return response()->json(['data' => $result ]);

        } catch (\Throwable $th) {
             \Log::debug($th->getMessage());

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

           $transaction  = PaystackService::initiateTransfer($request);

            return response()->json(['data' => $transaction ]);

        } catch (\Throwable $th) {
            \Log::debug($th->getMessage());
            if($th->getMessage() === "Insuficient fund!") {
               return response()->json(['message' => "Insuficient fund!"], 409);
            }
            return response()->json(['message' => "Unable to initiate Transfer"], 409);
        }

    }


    public function completeTransfer(Request $request, $transaction_id)
    {
        try {

             $transaction = PaystackService::completeTransfer($transaction_id);

            return response()->json(['data' => $transaction ]);

          } catch (\Throwable $th) {

            \Log::debug($th->getMessage());

            return response()->json(['message' => "Unable to Conplete Transfer"], 409);
        }

    }
}
