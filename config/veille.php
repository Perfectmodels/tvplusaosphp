<?php

return [
    'http_timeout' => (int) env('VEILLE_HTTP_TIMEOUT', 12),
    'source_time_budget' => (int) env('VEILLE_SOURCE_TIME_BUDGET', 60),
    'cycle_time_budget' => (int) env('VEILLE_CYCLE_TIME_BUDGET', 240),
    'deep_articles_per_source' => (int) env('VEILLE_DEEP_ARTICLES', 3),
    'gabon_ai_enabled' => (bool) env('GABON_VEILLE_AI_ENABLED', true),
    'gabon_ai_per_cycle' => (int) env('GABON_VEILLE_AI_PER_CYCLE', 3),
    'gabon_ai_daily_limit' => (int) env('GABON_VEILLE_AI_DAILY_LIMIT', 24),
    'gabon_ai_timeout' => (int) env('GABON_VEILLE_AI_TIMEOUT', 20),
    'gabon_ai_max_age_hours' => (int) env('GABON_VEILLE_AI_MAX_AGE_HOURS', 48),
];
