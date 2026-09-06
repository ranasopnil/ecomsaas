<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The store has hit what its plan allows.
 */
class LimitReached extends RuntimeException
{
    public function __construct(
        public readonly string $feature,
        public readonly ?int $limit,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forLimit(string $feature, int $limit): self
    {
        $label = config("features.{$feature}.label", $feature);

        return new self(
            $feature,
            $limit,
            "Your plan allows {$limit} ".mb_strtolower($label).'. Upgrade your plan to add more.'
        );
    }

    public static function forSwitch(string $feature): self
    {
        $label = config("features.{$feature}.label", $feature);

        return new self(
            $feature,
            null,
            mb_strtolower($label).' is not included in your plan. Upgrade your plan to use it.'
        );
    }

    public function render(Request $request): ?Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'feature' => $this->feature,
            ], 402);
        }

        return back()->withErrors(['plan' => $this->getMessage()]);
    }
}
