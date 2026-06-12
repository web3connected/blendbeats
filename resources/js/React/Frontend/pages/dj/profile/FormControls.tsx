import { X } from 'lucide-react';
import { useState } from 'react';

import { djGenres } from '@/config/djGenres';

export function Field({
  label,
  value,
  onChange,
  placeholder,
  type = 'text',
  required = false,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  type?: string;
  required?: boolean;
}) {
  return (
    <label className="grid gap-2">
      <span className="text-[11px] font-bold uppercase tracking-widest text-[#bbbbbb]">{label}</span>
      <input
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        required={required}
        className="h-12 w-full border border-[#333333] bg-[#080808] px-4 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
      />
    </label>
  );
}

export function SelectField({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: Array<{ value: string; label: string }>;
}) {
  return (
    <label className="grid gap-2">
      <span className="text-[11px] font-bold uppercase tracking-widest text-[#bbbbbb]">{label}</span>
      <select
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="h-12 w-full border border-[#333333] bg-[#080808] px-4 text-sm text-white outline-none transition-colors focus:border-primary"
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  );
}

export function TextAreaField({
  label,
  value,
  onChange,
  placeholder,
  required = false,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  required?: boolean;
}) {
  return (
    <label className="grid gap-2">
      <span className="text-[11px] font-bold uppercase tracking-widest text-[#bbbbbb]">{label}</span>
      <textarea
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        required={required}
        className="min-h-32 w-full resize-none border border-[#333333] bg-[#080808] p-4 text-sm leading-6 text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
      />
    </label>
  );
}

export function GenreTagPicker({
  label,
  selected,
  onChange,
  multiple = false,
  exclude = [],
  required = false,
}: {
  label: string;
  selected: string[];
  onChange: (genres: string[]) => void;
  multiple?: boolean;
  exclude?: string[];
  required?: boolean;
}) {
  const [query, setQuery] = useState('');
  const normalizedQuery = query.trim().toLowerCase();
  const availableGenres = djGenres.filter(
    (genre) =>
      !selected.includes(genre) &&
      !exclude.includes(genre) &&
      (!normalizedQuery || genre.toLowerCase().includes(normalizedQuery)),
  );

  const addGenre = (genre: string) => {
    onChange(multiple ? [...selected, genre] : [genre]);
    setQuery('');
  };

  return (
    <div className="grid gap-2">
      <span className="text-[11px] font-bold uppercase tracking-widest text-[#bbbbbb]">{label}</span>
      <div className="min-h-12 border border-[#333333] bg-[#080808] p-2 focus-within:border-primary">
        <div className="flex flex-wrap gap-2">
          {selected.map((genre) => (
            <span
              key={genre}
              className="inline-flex h-8 items-center gap-2 bg-primary px-3 text-xs font-bold uppercase tracking-widest text-white"
            >
              {genre}
              <button
                type="button"
                onClick={() => onChange(selected.filter((selectedGenre) => selectedGenre !== genre))}
                aria-label={`Remove ${genre}`}
              >
                <X size={13} />
              </button>
            </span>
          ))}
          <input
            type="text"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder={selected.length ? 'Add genre' : 'Search genres'}
            required={required && selected.length === 0}
            className="h-8 min-w-40 flex-1 bg-transparent px-2 text-sm text-white outline-none placeholder:text-[#555555]"
          />
        </div>
      </div>
      <div className="flex max-h-28 flex-wrap gap-2 overflow-y-auto border border-[#222222] bg-[#0b0b0b] p-2">
        {availableGenres.slice(0, 16).map((genre) => (
          <button
            key={genre}
            type="button"
            onClick={() => addGenre(genre)}
            className="h-8 border border-[#333333] px-3 text-xs font-semibold text-[#cccccc] transition-colors hover:border-primary hover:text-primary"
          >
            {genre}
          </button>
        ))}
      </div>
    </div>
  );
}
