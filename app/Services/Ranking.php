<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\Idea;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Scores ideas from 0 to 100 on three parts and combines them with adjustable weights.
 * This weighted formula stands in for a trained model; the three inputs stay the same.
 */
class Ranking
{
    public const DEFAULT_WEIGHTS = ['rel' => 40, 'eng' => 35, 'q' => 25];

    public function relevance(Idea $idea, ?Challenge $challenge): int
    {
        if (! $challenge) {
            return $idea->challenge ? $this->relevance($idea, $idea->challenge) : 35;
        }

        $text = Str::lower($idea->title.' '.$idea->summary.' '.$idea->body);
        $keywords = $challenge->keywords ?: [];
        $hits = collect($keywords)->filter(fn ($k) => str_contains($text, Str::lower($k)))->count();
        $r = count($keywords) ? $hits / count($keywords) * 100 : 0;
        if ($idea->challenge_id === $challenge->id) {
            $r = $r * .7 + 30;
        }

        return (int) min(100, round($r));
    }

    public function rawEngagement(Idea $idea): int
    {
        return $idea->likers_count + 3 * $idea->comments_count + 2 * $idea->shares;
    }

    public function quality(Idea $idea): int
    {
        $words = str_word_count($idea->body);

        return (int) min(100, round(min(45, $words / 4) + min(30, $idea->docs_count * 13) + $idea->stageIndex() * 6));
    }

    /** @return Collection<int, array{idea: Idea, rel: int, eng: int, q: int, total: int, outside: bool}> */
    public function rank(?Challenge $challenge, array $weights = self::DEFAULT_WEIGHTS): Collection
    {
        $all = Idea::query()->with(['author', 'challenge'])->withCount(['likers', 'comments', 'docs as docs_count'])->get();
        $max = max(1, (int) $all->map(fn ($i) => $this->rawEngagement($i))->max());
        $totalWeight = ($weights['rel'] + $weights['eng'] + $weights['q']) ?: 1;

        $pool = $challenge
            ? $all->filter(fn ($i) => $i->challenge_id === $challenge->id || $this->relevance($i, $challenge) >= 45)
            : $all;

        return $pool->map(function (Idea $i) use ($challenge, $max, $weights, $totalWeight) {
            $rel = $this->relevance($i, $challenge);
            $eng = (int) round($this->rawEngagement($i) / $max * 100);
            $q = $this->quality($i);

            return [
                'idea' => $i, 'rel' => $rel, 'eng' => $eng, 'q' => $q,
                'total' => (int) round(($weights['rel'] * $rel + $weights['eng'] * $eng + $weights['q'] * $q) / $totalWeight),
                'outside' => $challenge && $i->challenge_id !== $challenge->id,
            ];
        })->sortByDesc('total')->values();
    }
}
