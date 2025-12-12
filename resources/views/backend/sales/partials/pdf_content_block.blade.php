{{-- $block = [
      'placement'    => 'first'|'last',
      'content_type' => 'product'|'category'|'brand'|'offer',
      'items'        => collection/array of ['id','name','subtitle','image_url'],
      'no_of_poster' => int (optional)
] --}}

@if (!empty($block) && !empty($block['items']))
    @php
        $type      = $block['content_type'] ?? '';
        $placement = $block['placement']    ?? 'last';

        // Heading text
        $title = '';
        if ($type === 'offer') {
            $title = 'Featured Offers';
        } elseif ($type === 'product') {
            $title = 'Featured Products';
        } elseif ($type === 'category') {
            $title = 'Featured Categories';
        } elseif ($type === 'brand') {
            $title = 'Featured Brands';
        }

        // collection -> array
        $itemsArray = $block['items'] instanceof \Illuminate\Support\Collection
            ? $block['items']->all()
            : (array) $block['items'];

        // columns / posters per row
        $columns = isset($block['no_of_poster']) ? (int) $block['no_of_poster'] : 0;
        if ($columns <= 0)  $columns = 2;
        if ($columns < 1)   $columns = 1;
        if ($columns > 4)   $columns = 4;

        $cellWidth        = 100 / $columns;
        $imgMaxHeightGrid = 220;
    @endphp

    <table style="width:100%; margin:0; border-collapse:collapse; border-spacing:0; border:0;">

        {{-- Heading row (ek hi baar) --}}
        @if ($title !== '')
            <tr>
                <td colspan="{{ $columns }}" style="text-align:center; padding:4px 0 8px 0; border:0 !important;">
                    <span style="
                        font-weight:bold;
                        font-size:14px;
                        padding:2px 8px;
                        border-bottom:2px solid #174e84;
                        display:inline-block;
                    ">
                        {{ $title }}
                    </span>
                </td>
            </tr>
        @endif

        {{-- ================= SINGLE-COLUMN MODE (no_of_poster = 1) ================= --}}
        @if ($columns === 1)

            @foreach ($itemsArray as $card)
                @php
                    $name = $card['name']      ?? '';
                    $sub  = $card['subtitle']  ?? null;
                    $img  = $card['image_url'] ?? null;

                    $detailText = '';
                    if ($type === 'product' && !empty($sub)) {
                        $detailText = 'Part No: ' . $sub;
                    } elseif ($type === 'offer' && !empty($sub)) {
                        $detailText = 'Offer Code: ' . $sub;
                    }
                @endphp

                {{-- NAME (center) --}}
                @if ($name !== '')
                    <tr>
                        <td style="padding:2px 0 3px 0; text-align:center; border:0 !important;">
                            <span style="font-weight:700; font-size:14px; display:block; text-align:center; margin:0 auto;">
                                {{ $name }}
                            </span>
                        </td>
                    </tr>
                @endif

                {{-- IMAGE – chhota rakha hai taaki ~3 posters ek page par aa saken --}}
                <tr>
                    <td style="padding:0 0 2px 0; text-align:center; border:0 !important;">
                        @if (!empty($img))
                            <img src="{{ $img }}"
                                 alt=""
                                 style="
                                     max-width:100%;
                                     width:430px;
                                     max-height:200px;
                                     height:auto;
                                     display:block;
                                     margin:0 auto;
                                 ">
                        @else
                            <span style="font-size:12px; color:#999;">No Image</span>
                        @endif
                    </td>
                </tr>

                {{-- DETAIL LINE --}}
                @if ($detailText !== '')
                    <tr>
                        <td style="padding:0 0 6px 0; text-align:center; border:0 !important;">
                            <span style="font-size:11px; color:#555; display:block; text-align:center; margin:0 auto;">
                                {{ $detailText }}
                            </span>
                        </td>
                    </tr>
                @endif

                {{-- posters ke beech thoda gap --}}
                <tr>
                    <td style="height:6px; border:0 !important;"></td>
                </tr>
            @endforeach

        {{-- ================= GRID MODE (no_of_poster > 1) ================= --}}
        @else
            @php
                $rows = array_chunk($itemsArray, $columns);
            @endphp

            @foreach ($rows as $rowChunk)
                <tr>
                    @foreach ($rowChunk as $card)
                        @php
                            $name = $card['name']      ?? '';
                            $sub  = $card['subtitle']  ?? null;
                            $img  = $card['image_url'] ?? null;

                            $detailText = '';
                            if ($type === 'product' && !empty($sub)) {
                                $detailText = 'Part No: ' . $sub;
                            } elseif ($type === 'offer' && !empty($sub)) {
                                $detailText = 'Offer Code: ' . $sub;
                            }
                        @endphp

                        <td style="width:{{ $cellWidth }}%; padding:0 4px 10px 4px; vertical-align:top; border:0 !important; text-align:center;">

                            {{-- NAME (center) --}}
                            @if ($name !== '')
                                <div style="padding:4px 6px 4px 6px; text-align:center;">
                                    <span style="font-weight:700; font-size:13px; display:block; text-align:center; margin:0 auto;">
                                        {{ $name }}
                                    </span>
                                </div>
                            @endif

                            {{-- IMAGE --}}
                            <div style="text-align:center; padding:4px 0 4px 0;">
                                @if (!empty($img))
                                    <img src="{{ $img }}"
                                         alt=""
                                         style="max-height:{{ $imgMaxHeightGrid }}px; max-width:100%; height:auto; display:inline-block; margin:0 auto;">
                                @else
                                    <span style="font-size:11px; color:#999;">No Image</span>
                                @endif
                            </div>

                            {{-- DETAIL --}}
                            @if ($detailText !== '')
                                <div style="padding:0 6px 4px 6px; text-align:center;">
                                    <span style="font-size:11px; color:#555; display:block; text-align:center; margin:0 auto;">
                                        {{ $detailText }}
                                    </span>
                                </div>
                            @endif
                        </td>
                    @endforeach

                    {{-- agar last row me posters kam hon to blank cells se fill --}}
                    @for ($i = count($rowChunk); $i < $columns; $i++)
                        <td style="width:{{ $cellWidth }}%; padding:0 4px 10px 4px; border:0 !important;">&nbsp;</td>
                    @endfor
                </tr>
            @endforeach
        @endif
    </table>
@endif
