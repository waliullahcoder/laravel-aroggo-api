<?php

namespace App\Helpers;

class Response
{
    private static $instance;
    private $code = 200;
    private $data = [];
    private $response = [];

    private function __construct() {
    }

    public static function instance() {
        if( ! self::$instance instanceof self ) {
            self::$instance = new self;
        }
        return self::$instance;
    }
    public function setCode( $code ) {
        $this->code = (int) $code;
    }
    //can be fail|success|error
    public function setStatus( $status ) {
        $this->response['status'] = (string) $status;
    }
    public function setMessage( $message ) {
        $this->response['message'] = (string) $message;
    }
    public function loginRequired( $loginRequired ) {
        $this->response['loginRequired'] = (bool) $loginRequired;
    }

    public function addData( $key, $data ) {
        $this->data[ $key ] = $data;
    }
    public function appendData( $key, $data ) {
        if ( $key ) {
            $this->data[ $key ][] = $data;
        } else {
            $this->data[] = $data;
        }
    }
    public function setData( $data ) {
        $this->data = (array) $data;
    }
    public function getData( $key = '') {
        if( $key ){
            return ( \is_array( $this->data ) && isset( $this->data[ $key ] ) ) ? $this->data[ $key ] : [];
        } else {
            return $this->data;
        }
    }
    public function setResponse( $key, $value ) {
        $this->response[ $key ] = $value;
    }

    public function replaceResponse( $response ) {
        $this->response = (array) $response;
        $this->data = $this->response['data'] ?? [];
    }

    public function getResponse( $key = '' ) {
        $response = $this->response;
        $response['data'] = $this->data;
        if( $key ){
            return ( \is_array( $response ) && isset( $response[ $key ] ) ) ? $response[ $key ] : [];
        } else {
            return $response;
        }
    }

    public function send() {
        http_response_code( $this->code );
        header( 'Content-Type: application/json; charset=utf8' );

        $this->response['data']    = $this->data;

        /*Log::instance()->insert([
            'log_response_code' => $this->code,
            'log_response' => $this->response,
        ]);*/

        echo json_encode( $this->response );
        die;
    }

    public function sendMessage( $message, $status = 'fail' ){
        if ( $status ) {
            $this->setStatus( $status );
        }
        $this->setMessage( $message );
        $this->send();
    }
    public function sendData( $data, $status = 'fail' ){
        if ( $status ) {
            $this->setStatus( $status );
        }
        $this->setData( $data );
        $this->send();
    }
}