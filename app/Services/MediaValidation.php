<?php

declare(strict_types=1);

namespace App\Services;

use Spatie\MediaLibrary\MediaCollections\File;

/**
 * Contraintes appliquées aux fichiers acceptés par Media Library.
 *
 * Aucun endpoint d'upload n'existe à ce jour (les photos sont des URL), mais
 * ces règles ferment la porte à une future montée de fichiers dangereux :
 * exécutable, SVG (vecteur XSS) ou fichier volumineux.
 */
class MediaValidation
{
    /** Taille maximale d'une image de portrait (5 Mo). */
    public const MAX_IMAGE_OCTETS = 5 * 1024 * 1024;

    /** Taille maximale d'un document (15 Mo). */
    public const MAX_DOCUMENT_OCTETS = 15 * 1024 * 1024;

    /** Types image acceptés — le SVG est volontairement EXCLU (XSS). */
    public const TYPES_IMAGE = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    /** Types document acceptés. */
    public const TYPES_DOCUMENT = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
        'text/csv',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    /** Image de portrait : type image autorisé + taille max. */
    public static function imagePortrait(File $file): bool
    {
        return self::typeAutorise($file, self::TYPES_IMAGE)
            && $file->size <= self::MAX_IMAGE_OCTETS
            && ! self::estSvg($file);
    }

    /** Document : type autorisé + taille max, SVG exclu. */
    public static function document(File $file): bool
    {
        return self::typeAutorise($file, self::TYPES_DOCUMENT)
            && $file->size <= self::MAX_DOCUMENT_OCTETS
            && ! self::estSvg($file);
    }

    /** @param array<int, string> $types */
    public static function typeAutorise(File $file, array $types): bool
    {
        return in_array((string) $file->mimeType, $types, true);
    }

    /**
     * Un SVG peut contenir du script → jamais accepté, même déclaré en image.
     */
    public static function estSvg(File $file): bool
    {
        $nom = strtolower((string) $file->name);
        $mime = strtolower((string) $file->mimeType);

        return str_ends_with($nom, '.svg')
            || $mime === 'image/svg+xml'
            || $mime === 'image/svg';
    }
}
