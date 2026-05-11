<?php

namespace App\Models;

use App\Support\StreamingLabel;
use Illuminate\Database\Eloquent\Model;

class LibraryFolderPoster extends Model
{
    protected $fillable = [
        'folder_path',
        'poster_url',
    ];

    protected static function booted(): void
    {
        static::saving(function (LibraryFolderPoster $row): void {
            $row->folder_path = StreamingLabel::normalizeLibraryPath($row->folder_path);
        });
    }
}
