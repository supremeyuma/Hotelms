public function images()
    {
        return $this->morphMany(Image::class, 'imageable')->orderByDesc('is_primary')->orderBy('created_at');
    }

    /**
     * Primary image helper
     */
    public function primaryImage()
    {
        return $this->morphOne(Image::class, 'imageable')->where('is_primary', true);
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $img = $this->images()->where('is_primary', true)->first();
        if ($img) return $img->url;
        $first = $this->images()->first();
        return $first?->url;
    }