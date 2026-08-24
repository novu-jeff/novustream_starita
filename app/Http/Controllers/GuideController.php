<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class GuideController extends Controller
{
    public function index()
    {
        abort_unless(Gate::any(['admin', 'cashier']), 403);

        $guidePath = base_path('docs/ADMIN_CASHIER_OPERATIONS_GUIDE.txt');

        abort_unless(is_readable($guidePath), 404);

        $lines = preg_split('/\R/', file_get_contents($guidePath));
        $title = 'Operations Guide';
        $sections = [];
        $currentHeading = null;
        $currentContent = [];

        for ($index = 0; $index < count($lines); $index++) {
            $line = trim($lines[$index]);
            $nextLine = trim($lines[$index + 1] ?? '');

            if ($line !== '' && preg_match('/^[-=]{3,}$/', $nextLine)) {
                if ($currentHeading !== null) {
                    $sections[] = [
                        'heading' => $currentHeading,
                        'id' => Str::slug($currentHeading),
                        'blocks' => $this->formatContent(trim(implode("\n", $currentContent))),
                    ];
                }

                if ($nextLine[0] === '=') {
                    $title = $line;
                    $currentHeading = null;
                } else {
                    $currentHeading = $line;
                }

                $currentContent = [];
                $index++;

                continue;
            }

            if ($currentHeading !== null) {
                $currentContent[] = $lines[$index];
            }
        }

        if ($currentHeading !== null) {
            $sections[] = [
                'heading' => $currentHeading,
                'id' => Str::slug($currentHeading),
                'blocks' => $this->formatContent(trim(implode("\n", $currentContent))),
            ];
        }

        return view('guide.index', compact('title', 'sections'));
    }

    private function formatContent(string $content): array
    {
        $blocks = [];
        $paragraph = [];
        $listItems = [];
        $listType = null;

        $flushParagraph = function () use (&$blocks, &$paragraph) {
            if ($paragraph !== []) {
                $blocks[] = ['type' => 'paragraph', 'content' => implode(' ', $paragraph)];
                $paragraph = [];
            }
        };

        $flushList = function () use (&$blocks, &$listItems, &$listType) {
            if ($listItems !== []) {
                $blocks[] = ['type' => $listType, 'items' => $listItems];
                $listItems = [];
                $listType = null;
            }
        };

        foreach (preg_split('/\R/', $content) as $line) {
            $line = trim($line);

            if ($line === '') {
                $flushParagraph();
                $flushList();
                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/', $line, $matches)) {
                $flushParagraph();

                if ($listType !== 'ordered') {
                    $flushList();
                    $listType = 'ordered';
                }

                $listItems[] = $matches[1];
                continue;
            }

            if (preg_match('/^-\s+(.+)$/', $line, $matches)) {
                $flushParagraph();

                if ($listType !== 'unordered') {
                    $flushList();
                    $listType = 'unordered';
                }

                $listItems[] = $matches[1];
                continue;
            }

            $flushList();

            if (str_ends_with($line, ':')) {
                $flushParagraph();
                $blocks[] = ['type' => 'label', 'content' => rtrim($line, ':')];
                continue;
            }

            $paragraph[] = $line;
        }

        $flushParagraph();
        $flushList();

        return $blocks;
    }
}
