<?php

class comments_controller extends appcontroller{
	public function __construct(){
		parent::__construct();

		if($this->session->check("logged") == false) {
			$this->redirect("admin/login/nosession/");
		}
		
		$config = new configuration();
		$blogConfig = $config->getBlogConfiguration();
		$userConfig = $config->getUserConfiguration(1);

		$this->conf = $blogConfig;
		$this->userConf = $userConfig;
	}
	
	public function index($id = NULL){
		$this->view->setLayout("admin");
		$this->view->conf = $this->conf;
		$this->title_for_layout($this->l10n->__("Comentarios - Codice CMS"));

		$comment = new comment();
		$total_rows = $comment->countCommentsByPost();
		$page = $id;
		$page = (is_null($page)) ? 1 : $page ;
		$limit = $this->userConf['posts_per_page'];
		$offset = (($page-1) * $limit);
		$limitQuery = $offset.",".$limit;
		$targetpage = $this->path.'comments/';
		$pagination = $this->pagination->init($total_rows, $page, $limit, $targetpage);
		$this->view->pagination = $pagination;

		$comments = $comment->findAll(NULL, "ID DESC", $limitQuery, NULL);
		
		foreach($comments as $key => $value){
			$Post = new post();
			$post = $Post->findBy('ID',$value['ID_post']);

			$value['post'] = array(
				'urlfriendly' => $post['urlfriendly'],
				'title' => $post['title']
			);

			$value["content"] = utils::htmlentities($value["content"]);
			$value["content"] = utils::nl2br($value["content"]);

			$comments[$key] = $value;
		}
		
		$this->registry->comments = $comments;
		
		$this->plugin->call("comments_comment_content");
		$this->view->comments = $this->registry->comments;

		$this->render();
	}
	
	public function edit($id = NULL) {
		$id = (int) $id;
		if(!$id)$this->redirect('comments');

		$this->view->conf = $this->conf;
		$Comment = new comment();
		$comment = $Comment->find($id);

		$comment['content'] = utils::convert2HTML($comment['content']);

		$Post = new post();
		$post = $Post->findBy('ID',$comment['ID_post']);

		$comment['post'] = array(
			'urlfriendly' => $post['urlfriendly'],
			'title' => $post['title']
		);

		$this->view->comment = $comment;
		$this->view->id = $id;

		$statuses = array("publish", "waiting");

		$this->view->statuses = $statuses;
		if ($_SERVER["REQUEST_METHOD"]=="POST") {
			if(isset($_POST['cancelar'])){
				$this->redirect("comments");
			} else {
				###########
				# Las siguientes dos lineas no deberian estar pero algo anda mal con el ActiveRecord que no deja las variables
				# de las consultas que se realizan directamente desde dentro de algun metodo en el model con $this->db->query e interfiere
				# con el actualizar por que podria haber campos que no se requieren en la actualizacion.
				###########
				$comment = new comment();#######
				$comment->find($id);####### 

				$comment->prepareFromArray($_POST);

				$comment->save();
				$this->redirect("comments/edit/$id");
			}
		} else {
			$this->view->setLayout("admin");
			$this->title_for_layout($this->l10n->__("Editar comentario - Codice CMS"));
			$this->render();
		}
	}
	
	public function remove($id){
		$comment = new comment();
		$comment->find($id);
		$comment->delete();
		
		if($this->isAjax()){
			echo $id;
		}else{
			$this->redirect("comments");
		}
	}
	
	public function approve($id){
		$Comment = new comment();
		$Comment->find($id);
		
		if($Comment['type'] == 'pingback' or $Comment['type'] == 'trackback'){
			$Comment->setPingback();
		}
		
		$Comment['status'] = 'publish';
		$Comment->save();

		$this->registry->lastCommentID = $id;
		$this->plugin->call("comment_approbed");
		
		if($this->isAjax()){
			echo $id;
		}else{
			$this->redirect("comments");
		}
	}
}
