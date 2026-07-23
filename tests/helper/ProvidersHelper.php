<?php

declare(strict_types=1);

namespace Tests\Unit\helper;

final class ProvidersHelper
{
    public function getProviderList(\Brain\Faker\Providers $wpFaker): array
    {
        $posts = $wpFaker->posts(
            10,
            [
                'status' => 'publish',
                'type' => 'epi_embed',
            ]
        );

        return $posts;
    }
}
