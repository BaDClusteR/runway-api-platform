<?php

namespace ApiPlatform\Core\Storage;

interface ITokenStorage {
    public function getToken(): string;
}