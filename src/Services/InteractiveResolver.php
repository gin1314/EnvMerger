<?php

declare(strict_types=1);

namespace Gin\EnvMerger\Services;

use Gin\EnvMerger\DTO\Conflict;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InteractiveResolver
{
    public function __construct(
        private readonly SecretMasker $secretMasker = new SecretMasker()
    ) {
    }

    /**
     * @param array<string, Conflict> $conflicts
     * @param array<string, string> $added
     * @return array<string, string> Array of key => resolved value
     */
    public function resolve(array $conflicts, array $added = [], bool $showSecrets = false): array
    {
        $resolved = [];
        $acceptAllIncoming = false;
        $keepAllBase = false;

        $totalConflicts = count($conflicts);
        $currentIndex = 0;

        if ($totalConflicts > 0) {
            info("Found {$totalConflicts} conflicting key(s) between base and target.");
        }

        foreach ($conflicts as $key => $conflict) {
            $currentIndex++;

            if ($acceptAllIncoming) {
                $resolved[$key] = $conflict->incomingValue;
                continue;
            }

            if ($keepAllBase) {
                $resolved[$key] = $conflict->baseValue;
                continue;
            }

            $maskedBase = $this->secretMasker->mask($key, $conflict->baseValue, $showSecrets);
            $maskedIncoming = $this->secretMasker->mask($key, $conflict->incomingValue, $showSecrets);

            note(
                "Conflict [{$currentIndex}/{$totalConflicts}] for key: {$key}\n" .
                "  Base value:     {$maskedBase}\n" .
                "  Incoming value: {$maskedIncoming}"
            );

            $choice = select(
                label: "Select resolution for {$key}:",
                options: [
                    'incoming' => "Use Incoming [{$maskedIncoming}]",
                    'base' => "Keep Base [{$maskedBase}]",
                    'custom' => "Enter Custom Value...",
                    'all-incoming' => "Accept All Remaining Incoming",
                    'all-base' => "Keep All Remaining Base",
                ],
                default: 'incoming'
            );

            switch ($choice) {
                case 'incoming':
                    $resolved[$key] = $conflict->incomingValue;
                    break;

                case 'base':
                    $resolved[$key] = $conflict->baseValue;
                    break;

                case 'custom':
                    $customValue = text(
                        label: "Enter custom value for {$key}:",
                        default: $conflict->incomingValue,
                        required: false
                    );
                    $resolved[$key] = $customValue;
                    break;

                case 'all-incoming':
                    $acceptAllIncoming = true;
                    $resolved[$key] = $conflict->incomingValue;
                    break;

                case 'all-base':
                    $keepAllBase = true;
                    $resolved[$key] = $conflict->baseValue;
                    break;
            }
        }

        return $resolved;
    }
}
