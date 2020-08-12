<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;
use  App\User;
use  App\Bank;
use App\Transaction;


use Illuminate\Support\Facades\DB;


class TransactionController extends Controller
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


   public function list(Request $request)
   {
        $search = $request->search;
        $transactions = [];
        $authUser = Auth::user();
        if($search) {
             $transactions = Transaction::where('bank_account_name', 'like', "%{$search}%")
            ->where('user_id', $authUser->id)->get();
        }else {
             $transactions = Transaction::all();
        }

        return response()->json(['data' => $transactions]);

   }
}
