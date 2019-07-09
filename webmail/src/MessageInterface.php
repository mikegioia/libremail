<?php

namespace App;

interface MessageInterface
{
    public function copyTo(int $folderId);

    public function getByIds(array $ids);

    public function getSiblings(array $filters = [], array $options = []);
}
