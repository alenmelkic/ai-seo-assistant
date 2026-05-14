<?php

namespace AiSeoAssistant\SEO;

class SEOAdapterFactory
{
    private static ?SEOAdapterInterface $instance = null;

    public static function create(): SEOAdapterInterface
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $yoast = new YoastAdapter();
        if ($yoast->isActive()) {
            self::$instance = $yoast;
            return self::$instance;
        }

        $rankMath = new RankMathAdapter();
        if ($rankMath->isActive()) {
            self::$instance = $rankMath;
            return self::$instance;
        }

        self::$instance = new NullAdapter();
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
