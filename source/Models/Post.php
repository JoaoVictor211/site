<?php

namespace Source\Models;

use Source\Core\Model;

class Post extends Model
{
    private $all;
    public function __construct(bool $all = false)
    {
        $this->all = $all;
        parent::__construct("posts", ["id"], ["title", "uri", "subtitle", "content"]);
    }

    public function find(?string $terms = null, ?string $params = null, string $columns = "*")
    {
        if (!$this->all) {
            $terms = "status = :status AND post_at <= NOW()" . ($terms ? " AND {$terms}" : "");
            $params = "status=post" . ($params ? "&{$params}" : "");
        }
        return parent::find($terms, $params, $columns);
    }

    public function findByUri(string $uri, string $columns = "*")
    {
        $find = $this->find("uri = :uri", "uri={$uri}", $columns);
        return $find->fetch();
    }

    public function author(): ?User
    {
        if ($this->author) {
            return (new User)->findById($this->author);
        }
        return null;
    }

    public function category(): ?Category
    {
        if ($this->category) {

            return (new Category)->findById($this->category);
        }

        return null;
    }

    public function save()
    {
        if (!empty($this->id)) {
            $postId = $this->id;
            $this->update($this->safe(), "id = :id", "id={$postId}");
            if ($this->fail()) {
                $this->message->error("Erro ao atualizar, verifique os dados");
                return false;
            }
        }

        $this->data = $this->findById($postId)->data();
        return true;
    }
}
