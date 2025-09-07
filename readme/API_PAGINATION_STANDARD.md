# API Pagination Standard

## Overview

This document outlines the standardized approach to pagination in the SchoolSavvy API. We've implemented a consistent pagination format that provides all necessary metadata for client applications to handle paginated results effectively.

## Implementation

The pagination is implemented through the `paginatedSuccessResponse` method in the `BaseController` class, which all API controllers extend. This ensures a consistent pagination format across the entire application.

```php
protected function paginatedSuccessResponse($paginator, $resourceCollection, string $message = 'Success', int $code = 200): JsonResponse
{
    return response()->json([
        'status' => 'success',
        'message' => $message,
        'data' => $resourceCollection->response()->getData()->data,
        'meta' => [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'path' => $paginator->path(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ],
        'links' => [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ],
    ], $code);
}
```

## Pagination Response Format

All paginated API responses follow this standard format:

```json
{
  "status": "success",
  "message": "Data retrieved successfully",
  "data": [
    {
      // Resource data...
    },
    {
      // Resource data...
    }
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "path": "/api/endpoint",
    "per_page": 15,
    "to": 15,
    "total": 68
  },
  "links": {
    "first": "http://example.com/api/endpoint?page=1",
    "last": "http://example.com/api/endpoint?page=5",
    "prev": null,
    "next": "http://example.com/api/endpoint?page=2"
  }
}
```

## Pagination Parameters

All paginated endpoints accept these standard query parameters:

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| page | integer | The page number to retrieve | 1 |
| per_page | integer | Number of items per page | 15 |

Example: `/api/endpoint?page=2&per_page=25`

## Using Pagination in Controllers

To implement pagination in an API endpoint:

```php
public function index(Request $request)
{
    $perPage = $request->query('per_page', 15);
    
    $items = Model::with(['relation1', 'relation2'])
        ->where('condition', true)
        ->paginate($perPage);
    
    return $this->paginatedSuccessResponse(
        $items,
        ResourceCollection::collection($items),
        'Items retrieved successfully'
    );
}
```

## Client-Side Implementation

Frontend applications should handle paginated responses by:

1. Displaying the current items from the `data` array
2. Using the `meta` information to show pagination controls
3. Using the `links` to provide direct navigation to specific pages

Example implementation for a pagination component:

```javascript
// React example
function Pagination({ meta, links, onPageChange }) {
  return (
    <div className="pagination">
      <button 
        onClick={() => onPageChange(links.first)}
        disabled={!links.prev}
      >
        First
      </button>
      
      <button 
        onClick={() => onPageChange(links.prev)}
        disabled={!links.prev}
      >
        Previous
      </button>
      
      <span>
        Page {meta.current_page} of {meta.last_page}
      </span>
      
      <button 
        onClick={() => onPageChange(links.next)}
        disabled={!links.next}
      >
        Next
      </button>
      
      <button 
        onClick={() => onPageChange(links.last)}
        disabled={!links.next}
      >
        Last
      </button>
      
      <div>
        Showing {meta.from} to {meta.to} of {meta.total} items
      </div>
    </div>
  );
}
```

## Benefits

This standardized pagination approach:

1. Provides a consistent experience across the entire API
2. Includes all metadata needed for effective pagination controls
3. Supports flexible page sizes
4. Makes API responses more predictable for clients
5. Simplifies implementation for both backend and frontend developers
