<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NewCollectionEmail;
use App\Models\Collection;
use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * The owner's side of collections: assemble one quietly, then decide to
 * announce it.
 *
 * Creating and editing never notify anybody — announce() is the one action
 * that does.
 */
class CollectionController extends Controller
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    private const RULES = [
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:2000'],
        'cover_image' => ['nullable', 'image', 'max:8192'],
        'is_active' => ['sometimes', 'boolean'],
        'frame_ids' => ['sometimes', 'array'],
        'frame_ids.*' => ['exists:frames,id'],
        'contact_lens_ids' => ['sometimes', 'array'],
        'contact_lens_ids.*' => ['exists:contact_lenses,id'],
    ];

    public function index(): View
    {
        return view('admin.collections.index', [
            'collections' => Collection::query()
                ->withCount(['frames', 'contactLenses'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.collections.form', $this->formData(new Collection()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(self::RULES);

        $collection = Collection::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'cover_image' => $this->storeCover($request),
            'created_by' => $request->user()->id,
        ]);

        $this->syncItems($collection, $data);

        return redirect()->route('admin.collections.edit', $collection)
            ->with('status', 'Collection created. Announce it when you are ready.');
    }

    public function edit(Collection $collection): View
    {
        return view('admin.collections.form', $this->formData($collection));
    }

    public function update(Request $request, Collection $collection): RedirectResponse
    {
        $data = $request->validate(self::RULES);

        $collection->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            // Keep the existing cover when no new file was chosen.
            'cover_image' => $this->storeCover($request) ?? $collection->cover_image,
        ]);

        $this->syncItems($collection, $data);

        return redirect()->route('admin.collections.edit', $collection)
            ->with('status', 'Collection updated.');
    }

    public function destroy(Collection $collection): RedirectResponse
    {
        $collection->delete();

        return redirect()->route('admin.collections.index')->with('status', 'Collection deleted.');
    }

    /**
     * Declare the drop: publish it to the storefront and tell the customers.
     *
     * Guarded twice over, because this is the one irreversible button in the
     * console — it mails real people and cannot be recalled. An empty
     * collection is refused (nobody wants a mail pointing at an empty page),
     * and an already-announced one is refused so a double submit or a
     * revisited tab cannot blast the list twice. announced_at is what makes
     * that second guard work, and nothing ever clears it.
     */
    public function announce(Collection $collection): RedirectResponse
    {
        if ($collection->isAnnounced()) {
            return back()->with(
                'status',
                'That collection was already announced on '.$collection->announced_at->format('M j, Y').'.'
            );
        }

        if ($collection->itemCount() === 0) {
            return back()->withErrors(['announce' => 'Add at least one product before announcing this collection.']);
        }

        // Announcing implies publishing — an inbox full of links to a hidden
        // collection would be the worst of both worlds.
        $collection->forceFill(['is_active' => true])->save();

        $notified = $this->notifier->collectionAnnounced($collection);

        // Marketing e-mail stays behind the same opt-in the promo blasts
        // respect. Queued per recipient so one bad address cannot take the
        // rest of the batch down with it.
        $subscribers = User::query()
            ->where('role', 'customer')
            ->where('newsletter_opt_in', true)
            ->get();

        foreach ($subscribers as $subscriber) {
            Mail::to($subscriber->email)->queue(new NewCollectionEmail($collection));
        }

        $collection->forceFill([
            'announced_at' => now(),
            'recipients_count' => $notified,
        ])->save();

        return redirect()->route('admin.collections.index')->with(
            'status',
            sprintf(
                '"%s" announced — %d customer inbox(es), %d e-mail(s) queued.',
                $collection->name,
                $notified,
                $subscribers->count()
            )
        );
    }

    /** @return array<string, mixed> */
    private function formData(Collection $collection): array
    {
        return [
            'collection' => $collection,
            // Only sellable products are offerable — a collection is a
            // shopfront, not an archive.
            'frames' => Frame::where('is_active', true)->orderBy('brand')->orderBy('name')->get(),
            'contactLenses' => ContactLens::where('is_active', true)->orderBy('brand')->orderBy('name')->get(),
            'selectedFrameIds' => $collection->exists ? $collection->frames->pluck('id')->all() : [],
            'selectedLensIds' => $collection->exists ? $collection->contactLenses->pluck('id')->all() : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncItems(Collection $collection, array $data): void
    {
        // position keeps the owner's ordering, which the storefront page and
        // the e-mail both read back.
        $collection->frames()->sync($this->positioned($data['frame_ids'] ?? []));
        $collection->contactLenses()->sync($this->positioned($data['contact_lens_ids'] ?? []));
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, array{position: int}>
     */
    private function positioned(array $ids): array
    {
        $map = [];

        foreach (array_values($ids) as $position => $id) {
            $map[(int) $id] = ['position' => $position];
        }

        return $map;
    }

    private function storeCover(Request $request): ?string
    {
        return $request->hasFile('cover_image')
            ? $request->file('cover_image')->store('collections', 'public')
            : null;
    }
}
