<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Validator\Constraints\Email;
use App\Entity\Video;
use App\Entity\User;
use App\Services\JwtAuth;

class VideoController extends AbstractController {

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

    public function index(Request $request, JwtAuth $jwt_auth, PaginatorInterface $paginator) {
        //1.Recoger la cabecera
        $jwt = $request->headers->get('Authorization',null);
        //2.Chequear token
        $checked_token = $jwt_auth->checktoken($jwt);
        if($checked_token['auth']){
            $identity = $checked_token['decoded'];
            //3. Hacer consulta para paginar (DQL) similar a SQL pero usando objeto DOCTRINE
            $entitymanager = $this->getDoctrine()->getManager();
            $dql = "SELECT video FROM App\Entity\Video video WHERE video.user = {$identity->sub} ORDER BY video.id DESC";
            $query = $entitymanager->createQuery($dql);//result set
            //4. Recoger el parámetro page de la URL (GET)
            $page = $request->query->getInt('page', 1);
            $items_per_page = 3;
            //5.Invocar paginación
            $pagination = $paginator->paginate($query, $page, $items_per_page);
            $total = $pagination->getTotalItemCount();
            //6.Prepara array de datos a devolver
            $data_to_return = [
                'status'            => 'success',
                'code'              => 200,
                'total_item_count'  => $total,
                'page_actual'       => $page,
                'item_per_page'     => $items_per_page,
                'total_pages'       => ceil($total / $items_per_page),
                'user_id'           => $identity->sub,
                'videos'            => $pagination
                
            ];
        }else{
            $data_to_return = [
                'status'    => 'error',
                'code'      => 400,
                'message'   => 'No te podemos mostrar los videos'
            ];
        }
        //7. Devolver los datos
        return $this->resjson($data_to_return);
    }
    
    public function detail(Request $request, JwtAuth $jwt_auth, $id = null){
        $data_to_return = [
            'status'    => 'error',
            'code'      => 404,
            'message'   => 'Info de video no puede ser mostrada',
            'video_id'  => $id
        ]; 
        //1.Recoger token
        $jwt = $request->headers->get('Authorization',null);
        //2.Comprobar autenticidad de token
        $checked_token = $jwt_auth->checktoken($jwt);
        if($checked_token['auth']){
            //3.Recoger identidad del usuario
            $identity = $checked_token['decoded'];
            //4.Sacar el objeto del video en base al id
            $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                'id' => $id
            ]);
            //5.Comprobar si el video existe y es propiedad del usuario identificado
            if($video && is_object($video) && $video->getUser()->getId() == $identity->sub){
                //6.Devolver una respuesta
                $data_to_return = [
                    'status'    => 'success',
                    'code'      => 200,
                    'video'     => $video
                ]; 
            }            
        }        
        return $this->resjson($data_to_return);
    }
    
    public function create(Request $request, JwtAuth $jwt_auth){
        $data_to_return = [
            'status'    => 'error',
            'code'      => 400,
            'message'   => 'Ocurrió un error al guardar el video'
        ];
        //1. Comprobar el token
        $jwt = $request->headers->get('Authorization', null);
        $checked_token = $jwt_auth->checktoken($jwt);
        if($checked_token['auth']){
            $json = $request->get('json',null);
            //2. Comprobar que el json no llegue vacio
            if(!empty($json)){
                $params = json_decode($json,true);
                //3. Recoger y validar los datos recibidos
                $title = (!empty($params['title'])) ? $params['title'] : null;
                $description = (!empty($params['description'])) ? $params['description'] : null;
                $url = (!empty($params['url'])) ? $params['url'] : null;
                $status = (!empty($params['status'])) ? $params['status'] : null;
                //4. Encontrar el id del usuario autenticado y buscar el objeto del usuario entero
                $user_id = $checked_token['decoded']->sub;
                $user_object = $this->getDoctrine()->getRepository(User::class)->findOneBy([
                    'id'    => $user_id
                ]);
                //5. Instanciar el entity manager
                $entity_manager = $this->getDoctrine()->getManager();
                //6. Instanciar el objeto y setear los datos al repositorioguardar los datos en la BBDD
                $video = new Video();
                $video->setUser($user_object);
                $video->setTitle($title);
                $video->setDescription($description);
                $video->setUrl($url);
                $video->setStatus($status);
                $video->setCreatedAt(new \Datetime('now'));
                $video->setUpdatedAt(new \Datetime('now'));
                //7.Guardar video
                $entity_manager->persist($video);
                $video_saved = $entity_manager->flush();
                $data_to_return = [
                    'status'    => 'success',
                    'code'      => 200,
                    'message'   => 'Video guardado'
                ];
            }
            
        }
        return $this->resjson($data_to_return);        
    }
    
    public function update(Request $request, JwtAuth $jwt_auth, $id = null){
        $data_to_return = [
            'status'    => 'error',
            'code'      => 400,
            'message'   => 'No se ha podido actualizar el video'
        ];
        $jwt = $request->headers->get('Authorization',null);
        $checked_token = $jwt_auth->checktoken($jwt);
        $identity = $checked_token['decoded'];
        if($checked_token['auth']){
            $json = $request->get('json', null);
            $params = json_decode($json, true); //convertirlo a array
            $user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
                'id' => $identity->sub
            ]);
            $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                'id' => $id
            ]);
            if($video && is_object($video) && $video->getUser()->getId() == $identity->sub){
                $title = (!empty($params['title']) ? $params['title'] : null);
                $description = (!empty($params['description']) ? $params['description'] : null);
                $url = (!empty($params['url']) ? $params['url'] : null);
                $status = (!empty($params['status']) ? $params['status'] : null);
                $created_at = $video->getCreatedAt();
                $updated_at = new \Datetime('now');
                
                $video->setUser($user);
                $video->setTitle($title);
                $video->setDescription($description);
                $video->setUrl($url);
                $video->setStatus($status);
                $video->setCreatedAt($created_at);
                $video->setUpdatedAt($updated_at);
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($video);
                $entityManager->flush();
                
                $data_to_return = [
                    'status'    => 'success',
                    'code'      => 200,
                    'message'   => 'Video actualizado!!!',
                    'video'     => $video
                ];
            }
        }
        return $this->resjson($data_to_return);
    }
    
    public function delete(Request $request, JwtAuth $jwt_auth, $id = null){
        $data_to_return = [
            'status'    => 'error',
            'code'      => 400,
            'message'   => 'No se ha posido borrar el video',
            'video_id'  => $id
        ];
        
        //1. Recoger token
        $jwt = $request->headers->get('Authorization', null);
        //2. Comprobar si el token es correcto
        $checked_token = $jwt_auth->checktoken($jwt);
        if($checked_token['auth']){
            //3. Tomar identidad
            $identity = $checked_token['decoded'];
            //4. Instanciar el entity manager y el repositorio
            $em = $this->getDoctrine()->getManager();
            $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                'id' => $id
            ]);
            //5. Comprobar si el video existe y es propiedad del usuario identificado
            if($video && is_object($video) && $video->getUser()->getId() == $identity->sub){
                //6. Borrar el video
                $em->remove($video);
                $em->flush();
                $data_to_return = [
                    'status'    => 'success',
                    'code'      => 200,
                    'message'   => 'Video eliminado'
                ];
           }
        }
        return $this->resjson($data_to_return);
    }

}
