<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services;

class VectorSearch
{
    /**
     * Performs vector search on embeddings and returns sorted results
     * 
     * @param array<float> $searchVector The query vector to search with
     * @param array<array<array{content: string, embedding: array<float>}>> $embeddings The embeddings to search through
     * @param int $limit Maximum number of results to return
     * @return array<array{content: string, similarity: float}> Sorted results by similarity (descending)
     */
    public function searchVectors(
        array $searchVector,
        array $embeddings,
        int $limit = 5
    ): array {
        $results = [];

        foreach ($embeddings as $embedding) {
            if (!$embedding) {
                continue;
            }

            foreach ($embedding as $em) {
                if (!isset($em['embedding']) || !isset($em['content'])) {
                    continue;
                }

                $similarity = $this->cosineSimilarity(
                    $em['embedding'],
                    $searchVector
                );

                $results[] = [
                    'content' => $em['content'],
                    'similarity' => $similarity
                ];
            }
        }

        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Calculate cosine similarity between two vectors
     * 
     * @param array<float> $vec1 First vector
     * @param array<float> $vec2 Second vector
     * @return float Similarity score between -1 and 1 (1 = identical, 0 = orthogonal, -1 = opposite)
     */
    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        $len1 = count($vec1);
        $len2 = count($vec2);

        // Handle dimension mismatch - use the smaller dimension
        $len = min($len1, $len2);

        if ($len === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;

        for ($i = 0; $i < $len; $i++) {
            $v1 = $vec1[$i];
            $v2 = $vec2[$i];

            $dotProduct += $v1 * $v2;
            $magnitude1 += $v1 * $v1;
            $magnitude2 += $v2 * $v2;
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 === 0.0 || $magnitude2 === 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }
}
