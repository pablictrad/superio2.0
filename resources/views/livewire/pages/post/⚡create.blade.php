<?php

use Livewire\Component;

new class extends Component
{
    public string $title = '';
    public string $content = '';

    public function save()
    {
        $this->validate([
            'title' => 'required|max:255',
            'content' => 'required',
        ]);
        dd($this->title, $this->content);
        //Post::create($validated);
        //return $this->redirect('/post');
    }
};
?>

<div>
  <form wire:submit="save">
    <label>
        title
        <x-input type="text" placeholder="Title" wire:model="title" />
        @error('title')<span style="color:red;">{{$message}}</span>@enderror
    </label>

    <label>
        content
        <x-textarea placeholder="Content" wire:model="content" rows="5"></x-textarea>
        @error('content')<span style="color:red;">{{$message}}</span>@enderror
    </label>
    <x-button type="submit" wire:click="save">Save</x-button>
  </form>
</div>