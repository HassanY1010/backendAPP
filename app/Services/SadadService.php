<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SadadService
{
    protected $baseUrl;
    protected $user;
    protected $token;

    public function __construct()
    {
        $this->baseUrl = env('SADAD_BASE_URL', 'https://api.sadad360.com/api/tp/v1');
        $this->user = env('SADAD_USER');
        $this->token = env('SADAD_TOKEN');
    }

    /**
     * Common params for every request
     */
    protected function getAuthParams()
    {
        return [
            'USR' => $this->user,
            'TKN' => $this->token,
        ];
    }

    /**
     * Get Agent Balance
     */
    public function getBalance()
    {
        try {
            $response = Http::get($this->baseUrl, array_merge($this->getAuthParams(), [
                'AC' => 7400
            ]));
            
            return $response->json();
        } catch (\Exception $e) {
            Log::error("Sadad Balance Error: " . $e->getMessage());
            return ['rc' => -1, 'msg' => 'Connection Error'];
        }
    }

    /**
     * Create Service Payment
     * AC: 7100 (Bills) or 7200 (Bundles) or 7700 (E-Services)
     */
    public function pay(array $data)
    {
        $payload = array_merge($this->getAuthParams(), $data);
        
        Log::info("Sadad Payment Request: ", $payload);

        try {
            $response = Http::post($this->baseUrl, $payload);
            $result = $response->json();
            
            Log::info("Sadad Payment Response: ", $result ?? []);
            
            return $result;
        } catch (\Exception $e) {
            Log::error("Sadad Payment Error: " . $e->getMessage());
            return ['rc' => -1, 'msg' => 'Connection Error'];
        }
    }

    /**
     * Check Transaction Status
     */
    public function checkStatus($ref)
    {
        try {
            $response = Http::get($this->baseUrl, array_merge($this->getAuthParams(), [
                'AC' => 3001,
                'REF' => $ref,
            ]));

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Sadad Status Check Error: " . $e->getMessage());
            return ['rc' => -1, 'msg' => 'Connection Error'];
        }
    }
}
