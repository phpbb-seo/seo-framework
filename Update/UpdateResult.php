<?php
declare(strict_types=1);

namespace phpbbseo\framework\Update;

/**
 * Normalized immutable Value Object containing update status and release info.
 * Decouples the ACP presentation from the remote provider implementation.
 */
final class UpdateResult
{
    public const STATUS_UP_TO_DATE       = 'up_to_date';
    public const STATUS_UPDATE_AVAILABLE = 'update_available';
    public const STATUS_AHEAD            = 'ahead';
    public const STATUS_UNAVAILABLE      = 'unavailable';

    public function __construct(
        private string $currentVersion,
        private string $latestVersion,
        private bool $updateAvailable,
        private bool $isAhead,
        private string $status,
        private ?string $releaseUrl = null,
        private ?string $downloadUrl = null,
        private ?string $publishedAt = null,
        private int $checkedAt = 0,
        private ?string $errorMessage = null
    ) {}

    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    public function getLatestVersion(): string
    {
        return $this->latestVersion;
    }

    public function isUpdateAvailable(): bool
    {
        return $this->updateAvailable;
    }

    public function isAhead(): bool
    {
        return $this->isAhead;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReleaseUrl(): ?string
    {
        return $this->releaseUrl;
    }

    public function getDownloadUrl(): ?string
    {
        return $this->downloadUrl;
    }

    public function getPublishedAt(): ?string
    {
        return $this->publishedAt;
    }

    public function getCheckedAt(): int
    {
        return $this->checkedAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function toArray(): array
    {
        return [
            'current_version'  => $this->currentVersion,
            'latest_version'   => $this->latestVersion,
            'update_available' => $this->updateAvailable,
            'is_ahead'         => $this->isAhead,
            'status'           => $this->status,
            'release_url'      => $this->releaseUrl,
            'download_url'     => $this->downloadUrl,
            'published_at'     => $this->publishedAt,
            'checked_at'       => $this->checkedAt,
            'error_message'    => $this->errorMessage,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['current_version'] ?? ''),
            (string) ($data['latest_version'] ?? ''),
            (bool) ($data['update_available'] ?? false),
            (bool) ($data['is_ahead'] ?? false),
            (string) ($data['status'] ?? self::STATUS_UNAVAILABLE),
            isset($data['release_url']) ? (string) $data['release_url'] : null,
            isset($data['download_url']) ? (string) $data['download_url'] : null,
            isset($data['published_at']) ? (string) $data['published_at'] : null,
            (int) ($data['checked_at'] ?? 0),
            isset($data['error_message']) ? (string) $data['error_message'] : null
        );
    }
}
