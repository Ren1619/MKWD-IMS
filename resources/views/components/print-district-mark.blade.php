@php($markId = 'district-'.\Illuminate\Support\Str::uuid())
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240" role="img" aria-label="Metro Kidapawan Water District seal" {{ $attributes }}>
    <defs>
        <path id="{{ $markId }}-name" d="M 107 23 A 96 96 0 0 1 215 143" />
        <path id="{{ $markId }}-motto" d="M 15 154 A 112 112 0 0 0 225 154" />
    </defs>
    <circle cx="120" cy="113" r="94" fill="#100568" />
    <path d="M 45 165 A 94 94 0 1 0 102 21 L 103 11 L 80 31 L 107 42 L 105 31 A 84 84 0 1 1 53 159 Z" fill="#0bd600" />
    <text fill="white" font-family="Arial,sans-serif" font-size="6.2" font-weight="700" letter-spacing="1.5"><textPath href="#{{ $markId }}-name">METRO KIDAPAWAN WATER DISTRICT</textPath></text>
    <text fill="#0bd600" font-family="Arial,sans-serif" font-size="34" font-weight="700" x="39" y="91">24</text>
    <text fill="#0bd600" font-family="Arial,sans-serif" font-size="32" font-weight="700" x="97" y="116">7</text>
    <path d="M111 47 L86 89 M83 106 L58 148" stroke="#0bd600" stroke-width="4" /><circle cx="86" cy="99" r="4" fill="#0bd600" />
    <path d="M153 50 C145 42 135 48 138 55 C140 62 148 57 154 58 L154 68 L152 68 L152 77 C143 81 144 86 147 90 C140 95 131 91 125 98 C121 102 121 110 118 114 L120 120 C126 123 133 124 139 121 L141 115 C136 111 135 104 142 103 C151 101 155 109 169 106 L184 107 L186 91 L169 90 C162 90 159 86 160 80 L160 68 L158 68 L158 58 C164 55 168 59 172 56 C177 52 170 46 164 49 Z" fill="#e1e1e1" />
    <path d="M123 122 L112 184 Q120 188 132 184 L137 122" fill="#00b5ee" /><path d="M126 124 L118 180 M133 124 L127 182" stroke="#85efff" stroke-width="2" />
    <path d="M23 153 C48 138 60 168 83 166 C66 161 66 151 55 151 C42 151 41 139 56 142 C83 143 80 174 107 181 C135 192 155 168 165 157 C182 135 196 153 177 163 C189 160 198 151 207 155 C223 164 202 170 189 173 C178 176 188 182 194 178 C174 205 93 219 59 183 C49 173 48 159 32 160 C14 164 8 156 23 153 Z" fill="#00cce8" />
    <path d="M50 154 C78 155 68 185 112 190 C145 197 159 178 171 170 C156 200 101 210 70 185 Z" fill="#00aef0" />
    <text fill="#100568" font-family="Arial,sans-serif" font-size="12.5" font-weight="900" letter-spacing="1"><textPath href="#{{ $markId }}-motto" startOffset="50%" text-anchor="middle">POTABLE, ACCESSIBLE, UNLIMITED</textPath></text>
</svg>
