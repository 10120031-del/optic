<?php

namespace Tests\Feature;

use App\Models\Frame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrameImageDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function frame(): Frame
    {
        return Frame::create([
            'name' => 'T', 'brand' => 'B', 'sku' => 'SKU-'.uniqid(),
            'lens_width' => 50, 'lens_height' => 40, 'bridge_width' => 18, 'temple_length' => 140,
            'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim',
            'gender' => 'unisex', 'price' => 10, 'stock' => 1, 'is_active' => true,
        ]);
    }

    public function test_edit_page_renders_remove_button_wired_to_delete_form(): void
    {
        Storage::fake('public');
        $frame = $this->frame();
        $img = $frame->images()->create(['path' => 'frames/a.jpg', 'is_primary' => true, 'sort_order' => 1]);

        $res = $this->actingAs($this->admin())->get(route('admin.frames.edit', $frame));
        $res->assertOk();
        $res->assertSee('form="delete-image-'.$img->id.'"', false);
        $res->assertSee('id="delete-image-'.$img->id.'"', false);
        $res->assertSee(route('admin.frames.images.destroy', [$frame, $img]), false);
    }

    public function test_delete_removes_row_and_file_and_promotes_next_primary(): void
    {
        Storage::fake('public');
        $frame = $this->frame();
        Storage::disk('public')->put('frames/a.jpg', 'aaa');
        Storage::disk('public')->put('frames/b.jpg', 'bbb');
        $frame->images()->create(['path' => 'frames/a.jpg', 'is_primary' => true, 'sort_order' => 1]);
        $frame->images()->create(['path' => 'frames/b.jpg', 'is_primary' => false, 'sort_order' => 2]);

        [$a, $b] = $frame->images()->get()->all();
        Storage::disk('public')->assertExists($a->path);

        $this->actingAs($this->admin())
            ->delete(route('admin.frames.images.destroy', [$frame, $a]))
            ->assertRedirect();

        $this->assertDatabaseMissing('frame_images', ['id' => $a->id]);
        Storage::disk('public')->assertMissing($a->path);
        $this->assertTrue($b->fresh()->is_primary, 'next image should become primary');
        Storage::disk('public')->assertExists($b->path);
    }

    public function test_cannot_delete_image_belonging_to_another_frame(): void
    {
        Storage::fake('public');
        $a = $this->frame();
        $b = $this->frame();
        $img = $b->images()->create(['path' => 'frames/b.jpg', 'is_primary' => true, 'sort_order' => 1]);

        $this->actingAs($this->admin())
            ->delete(route('admin.frames.images.destroy', [$a, $img]))
            ->assertNotFound();

        $this->assertDatabaseHas('frame_images', ['id' => $img->id]);
    }

    public function test_non_admin_cannot_delete(): void
    {
        Storage::fake('public');
        $frame = $this->frame();
        $img = $frame->images()->create(['path' => 'frames/a.jpg', 'is_primary' => true, 'sort_order' => 1]);

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.frames.images.destroy', [$frame, $img]))
            ->assertForbidden();

        $this->assertDatabaseHas('frame_images', ['id' => $img->id]);
    }
}
