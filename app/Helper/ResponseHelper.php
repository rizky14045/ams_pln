<?php 

namespace App\Helper;

class ResponseHelper {
    
    public static function response($message,$errors,$data,$code_status){

        return response()->json([
            'message' => $message, // pesan error
            'data' => $data, // data
            'errors' => $errors // pesan error message
        ], $code_status);

    }
}