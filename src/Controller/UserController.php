<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;
use App\Entity\User;
use App\Services\JwtAuth;

class UserController extends AbstractController {

    //Función para serializar un objeto JSON de gran tamaño y poder ser 
    //enviado como respuesta al FRONTEND
    private function resjson($data) {
        //1. Serializar datos con servicio serializer, indicando el formato
        $json = $this->get('serializer')->serialize($data, 'json');
        //2. Crear Response con HttpFoundation
        $response = new Response();
        //3. Asignar contenido a la respuesta
        $response->setContent($json);
        //4. Indicar formato de respuesta que viajará en la cabecera
        $response->headers->set('Content-Type', 'application/json');
        //5. Devolver la respuesta
        return $response;
    }

    public function index() {
        $user_repo = $this->getDoctrine()->getRepository(User::class);
        $users = $user_repo->findAll();

        return $this->resjson($users);
    }

    public function create(Request $request) {
        //1. Recibir "JSON String" enviado por POST
        $json = $request->get('json', null);
        //2.Decodificar JSON string a objeto PHP
        $params = json_decode($json);
        //3. Respuesta por defecto
        $data_to_return = [
            'status' => 'error',
            'code' => 400,
            'message' => 'El usuario no se ha creado'
        ];
        //4. Comprobar si el email existe (usuario duplicado)
        $user_repo = $this->getDoctrine()->getRepository(User::class);
        $duplicate_email = $user_repo->findBy([
            'email' => $params->email
        ]);
        if ($duplicate_email) {
            $data_to_return = [
                'status' => 'error',
                'code' => 400,
                'message' => 'Usuario ya existe'
            ];
        } else { //usuario no existe en la base de datos... seguimos
            //5. Comprobar y validar datos
            if ($json != null) {
                $name = (!empty($params->name)) ? $params->name : null;
                $surname = (!empty($params->surname)) ? $params->surname : null;
                $email = (!empty($params->email)) ? $params->email : null;
                $password = (!empty($params->password)) ? $params->password : null;

                $validator = Validation::createValidator();
                $validate_email = $validator->validate($email, [
                    new Email()
                ]);
                if ($name && $surname && $email && $password && count($validate_email) == 0) {
                    //5.1. Si la validación es correcta. Cifrar contraseña 
                    $pwd = hash('sha256', $password);

                    //5.2. Crear el objeto del usuario asignándole los datos recibidos 
                    $user = new User();
                    $user->setName($name);
                    $user->setSurname($surname);
                    $user->setEmail($email);
                    $user->setPassword($pwd); //cifrada
                    $user->setRole('ROLE_USER');
                    $user->setCreatedAt(new \Datetime('now'));
                    $user->setUpdatedAt(new \Datetime('now'));

                    //5.3. Guardar el usuarios en la base de datos
                    $entityManager = $this->getDoctrine()->getManager();
                    $entityManager->persist($user);
                    $user_saved = $entityManager->flush();

                    //5.4 Crear mensaje satisfactorio
                    $data_to_return = [
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'Usuario creado correctamente'
                    ];
                } else {
                    $data_to_return = [
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Error en los datos enviados'
                    ];
                }
            }
        }
        //6. Crear respuesta en json para devolver al front end
        return new JsonResponse($data_to_return);
    }

    //inyectando el servicio
    public function login(Request $request, JwtAuth $jwt_auth) {
        //1. Recibir "JSON String" enviado por POST y convertirlo a objeto PHP
        $json = $request->get('json', null);
        $params = json_decode($json); //objeto php
        //2. Crear respuesta por defecto a devolver
        $data_to_return = [
            'status' => 'error',
            'code' => 400,
            'message' => 'Error al hacer login'
        ];
        //3. Comprobar que llegan datos por POST y validar dichos datos
        if ($json != null) {
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
            $getToken = (!empty($params->getToken)) ? $params->getToken : null; //flag para saber si devolvemos los datos del usuario que incluye el token

            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if (!empty($email) && !empty($password) && count($validate_email) == 0) {
                //4. Cifrar la contraseña
                $pwd = hash('sha256', $password);
                //5. Si todo es válido, llamaremos a un servicio para crear un token (jwt)
                $token = $jwt_auth->signup($email, $pwd, $getToken);
                $data_to_return = $token;
            }
        }
        //6. Si los datos coinciden, crear respuesta satisfactoria
        return new JsonResponse($data_to_return);
    }

    public function update(Request $request, JwtAuth $jwt_auth) {
        $data_to_return = [
            'status' => 'error',
            'code' => 404,
            'message' => 'No se ha podido actualizar usuario, inicie sesión nuevamente'
        ];
        //1. Recoger la cabecera de autenticación
        $jwt = $request->headers->get('Authorization');

        //2. Chequear el token
        $checking_token = $jwt_auth->checktoken($jwt);

        //3. Si el token es correcto, continuar
        if ($checking_token['auth']) {
            //4. Recoger los datos que se actualizarán.
            $json = $request->get('json', null);
            $params = json_decode($json);
            //5.Comprobar y validar datos
            if ($json != null) {
                $name = (!empty($params->name)) ? $params->name : null;
                $surname = (!empty($params->surname)) ? $params->surname : null;
                $email = (!empty($params->email)) ? $params->email : null;

                $validator = Validation::createValidator();
                $validate_email = $validator->validate($email, [
                    new Email()
                ]);
                if ($name && $surname && $email && count($validate_email) == 0) {
                    //6. Conseguir el id del usuario identificado.
                    $user_id = $checking_token['decoded']->sub;
                    //7. Conseguir entity manager.
                    $entityManager = $this->getDoctrine()->getManager();
                    //8. Obtener repositorio y verificar si el email no está duplicado.
                    $user_repo = $this->getDoctrine()->getRepository(User::class);
                    $duplicate_email = $user_repo->findBy([
                        'email' => $email
                    ]);
                    if (count($duplicate_email) == 0 || $checking_token['decoded']->email == $email) {
                        //9.Obtener el usuario y setearle los nuevos datos 
                        $user = $user_repo->find($user_id);
                        $user->setName($name);
                        $user->setSurname($surname);
                        $user->setEmail($email);
                        //10. GUARDAR
                        $entityManager->persist($user);
                        $saved = $entityManager->flush();
                        //11. Agregar usuario actualizado a la respuesta.
                        $user_updated = $user_repo->findOneBy([
                            'id' => $user_id
                        ]);
                        $data_to_return = [
                            'status' => 'success',
                            'code' => 200,
                            'user_updated' => $user_updated
                        ];
                    } else {

                        $data_to_return = [
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Email ya existe'
                        ];
                    }
                }
            }
        }
        //12. Retornar la respuesta en json con "serialize"
        return $this->resjson($data_to_return);
    }

}
