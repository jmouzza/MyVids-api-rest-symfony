<?php

namespace App\Services;

use Firebase\JWT\JWT;
use App\Entity\User;

class JwtAuth {

    public $key;
    public $em;

    public function __construct($entity_manager) {
        $this->em = $entity_manager;
        /*
         * De esta manera está disponible el servicio inyectado "entity manager" 
         * dentro del servicio, y podremos realizar consultas a la BBDD 
         */
        $this->key = "clave--0208--0509";
    }

    public function signup($email, $pwd, $getToken = null) {
        //1. Comprobar si el usuario y el password coinciden con los datos en la BBDD
        $user = $this->em->getRepository(User::class)->findOneBy([
            'email' => $email,
            'password' => $pwd
        ]);
        //2. Si existe el usuario y coincide la clave, generar el token
        if (is_object($user)) {
            $token_payload = array(
                'sub' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'surname' => $user->getSurname(),
                'iat' => time(),
                'exp' => time() + (7 * 24 * 60 * 60)
            );
            $jwt = JWT::encode($token_payload, $this->key, 'HS256');
            $data_to_return = [
                'status' => 'success',
                'jwt'    => $jwt
            ];
            //3.Comprobar el flag gettoken, Condición
            if ($getToken === true || $getToken == 'true') {
                $decoded = JWT::decode($jwt, $this->key, ['HS256']);
                $data_to_return = [
                    'status'    => 'success',
                    'payload'   => $decoded,
                    'jwt'       => $jwt
                ];
            }
        } else {
            $data_to_return = [
                'status' => 'error',
                'code' => 400,
                'message' => 'Login incorrecto'
            ];
        }
        //4. Devolver los datos
        return $data_to_return;
    }
    
    public function checktoken($jwt){
        $data_to_return = [
          'auth'    => false  
        ];
        try{
            $jwt = str_replace('"','',$jwt);
            $decoded = JWT::decode($jwt, $this->key, ['HS256']);
        } catch (\UnexpectedValueException $e) {
            $data_to_return = ['auth' => false];
        } catch (\DomainException $e) {
            $data_to_return = ['auth' => false];
        }
        if(!empty($decoded) && is_object($decoded) && isset($decoded)){
            $data_to_return = [
                'auth'      => true,
                'decoded'   => $decoded
            ];
        }else{
            $data_to_return = [
                'auth'      => false
            ];
        }
        
        return $data_to_return;
        
    }   

}
