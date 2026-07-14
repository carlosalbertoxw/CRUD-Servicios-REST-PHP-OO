<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Model\NoteRequest;
use App\Repository\NoteRepository;

/**
 * Endpoints REST de notas. Todas las operaciones quedan acotadas al cliente
 * autenticado ($ownerKeyId): un cliente nunca ve ni toca notas de otro.
 */
final class NotesController
{
    private const MAX_PAGE_SIZE = 100;
    private const MAX_SEARCH_LENGTH = 100;

    public function __construct(private readonly NoteRepository $notes)
    {
    }

    /** GET /api/notes — lista paginada (keyset) con búsqueda opcional. */
    public function list(Request $request, array $params, string $ownerKeyId): Response
    {
        $errors = [];

        $afterId = $this->parseInt($request->queryParam('afterId', '0'), 'afterId', 0, PHP_INT_MAX, $errors);
        $pageSize = $this->parseInt($request->queryParam('pageSize', '20'), 'pageSize', 1, self::MAX_PAGE_SIZE, $errors);

        $search = $request->queryParam('search');
        if ($search !== null && mb_strlen($search) > self::MAX_SEARCH_LENGTH) {
            $errors['search'][] = 'La búsqueda no puede superar los 100 caracteres.';
        }

        if ($errors !== []) {
            return $this->validationProblem($errors);
        }

        $page = $this->notes->getPage($ownerKeyId, $afterId, $pageSize, $search);

        return Response::json($page);
    }

    /** GET /api/notes/{id} — obtiene una nota por id. */
    public function getById(Request $request, array $params, string $ownerKeyId): Response
    {
        $note = $this->notes->getById($ownerKeyId, (int) $params['id']);

        return $note === null ? $this->notFound() : Response::json($note);
    }

    /** POST /api/notes — crea una nota. */
    public function create(Request $request, array $params, string $ownerKeyId): Response
    {
        try {
            $body = NoteRequest::fromJson($request->rawBody);
        } catch (ValidationException $e) {
            return $this->validationProblem($e->errors());
        }

        $note = $this->notes->create($ownerKeyId, $body->title, $body->text);

        return Response::json($note, 201, ['Location' => '/api/notes/' . $note->id]);
    }

    /** PUT /api/notes/{id} — actualiza una nota existente. */
    public function update(Request $request, array $params, string $ownerKeyId): Response
    {
        try {
            $body = NoteRequest::fromJson($request->rawBody);
        } catch (ValidationException $e) {
            return $this->validationProblem($e->errors());
        }

        $updated = $this->notes->update($ownerKeyId, (int) $params['id'], $body->title, $body->text);

        return $updated ? Response::noContent() : $this->notFound();
    }

    /** DELETE /api/notes/{id} — elimina una nota. */
    public function delete(Request $request, array $params, string $ownerKeyId): Response
    {
        $deleted = $this->notes->delete($ownerKeyId, (int) $params['id']);

        return $deleted ? Response::noContent() : $this->notFound();
    }

    /**
     * @param array<string,string[]> $errors
     */
    private function parseInt(?string $value, string $field, int $min, int $max, array &$errors): int
    {
        if ($value === null) {
            return $min;
        }
        if (!ctype_digit($value)) {
            $errors[$field][] = "El parámetro {$field} debe ser un entero.";

            return $min;
        }
        $parsed = (int) $value;
        if ($parsed < $min || $parsed > $max) {
            $errors[$field][] = "El parámetro {$field} debe estar entre {$min} y {$max}.";

            return $min;
        }

        return $parsed;
    }

    /** @param array<string,string[]> $errors */
    private function validationProblem(array $errors): Response
    {
        return Response::problem(
            400,
            'Uno o más errores de validación ocurrieron.',
            null,
            ['errors' => $errors],
        );
    }

    private function notFound(): Response
    {
        return Response::problem(404, 'No encontrado.', 'La nota solicitada no existe.');
    }
}
