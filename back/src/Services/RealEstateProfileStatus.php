<?php

namespace App\Services;

final class RealEstateProfileStatus
{
    public const DRAFT = 0;
    public const INITIAL_REVIEW = 1;
    public const APPROVED = 2;
    public const REJECTED = 3;
    public const CHANGES_PENDING = 4;
}