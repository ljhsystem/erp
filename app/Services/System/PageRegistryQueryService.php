<?php

namespace App\Services\System;

use App\Models\System\PageRegistryModel;
use PDO;

class PageRegistryQueryService
{
    private PageRegistryModel $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new PageRegistryModel($pdo);
    }

    public function getResolverRows(): array
    {
        return $this->model->getResolverRows();
    }

    public function getAll(): array
    {
        return $this->model->getAll();
    }
}
