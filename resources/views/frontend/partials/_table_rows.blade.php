{{-- resources/views/frontend/partials/_table_rows.blade.php --}}
@php
    $balance =0 ;
    $drBalance = 0;
    $crBalance = 0;
    $clossingDrBalance="";
    $clossingCrBalance="";
@endphp
@if(count($data) > 0)
    @foreach($data as $gKey=>$gValue)
        @if($gValue['ledgername'] != 'closing C/f...')
            <tr style="{{ isset($gValue['overdue_status']) && $gValue['overdue_status'] == 'Overdue' ? 'background-color: #ff00006b;' : (isset($gValue['overdue_status']) && $gValue['overdue_status'] == 'Pertial Overdue' ? 'background-color: #ff00002b;' : '') }}">
                <td><a href="#">{{ date('d-m-Y', strtotime($gValue['trn_date'])) }}</a></td>
                <td>
                    @if($gValue['ledgername'] == 'Opening b/f...')
                        Opening Balance
                    @else
                        {{ strtoupper($gValue['vouchertypebasename']) }}
                    @endif
                    @if(trim($gValue['narration']) != "")
                        <p><small>({{$gValue['narration']}})</small></p>
                    @endif
                    {!! isset($gValue['overdue_status']) ? '<p><small>('.$gValue['overdue_status'].')</small></p>' : '' !!}
                </td>
                <td>{{ $gValue['trn_no'] != "" ? $gValue['trn_no'] : '' }}</td>
                <td><span style="color:#ff0707;">{{ $gValue['dramount'] != "0.00" ? single_price($gValue['dramount']) : '' }}</spn></td>
                <td><span style="color:#8bc34a;">{{ $gValue['cramount'] != "0.00" ? single_price($gValue['cramount']) : '' }}</span></td>
                <td>
                @if($gValue['ledgername'] == 'Opening b/f...')
                    @php
                        $balance = $gValue['dramount'] != "0.00" ? $gValue['dramount'] : -$gValue['cramount'];
                    @endphp
                @else
                    @php
                        $balance += $gValue['dramount'] - $gValue['cramount'];
                    @endphp
                @endif
                {{ single_price($balance)}}
                </td>
                <td class="text-center">
                    @php
                        if ($gValue['dramount'] != 0.00) {
                            $drBalance =$drBalance + $gValue['dramount'];
                        } 
                        if($gValue['cramount'] != '0.00') {
                            $crBalance = $crBalance + $gValue['cramount'];
                        }
                    @endphp

                    {!! $drBalance > $crBalance ? '<span style="color:#ff0707;">Dr</span>' : '<span style="color:#8bc34a;">Cr</span>' !!}

                </td>
                <td class="text-center">{{ isset($gValue['overdue_by_day'])?$gValue['overdue_by_day']:''}}</td>
            </tr>
        @else
            @php
                $clossingDrBalance = $gValue['dramount'];
                $clossingCrBalance = $gValue['cramount'];
            @endphp     
        @endif
    @endforeach
    <tr>
        <td></td>
        <td></td>
        <td><strong>Total</strong></td>
        <td>{{ single_price($drBalance) }}</td>
        <td>{{ single_price($crBalance) }}</td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
    <tr>
        <td></td>
        <td></td>
        <td><strong>Clossing Balance</strong></td>
        <td>{{ ($clossingCrBalance != "0.00") ? single_price($clossingCrBalance) : "" }}</td>
        <td>{{ ($clossingDrBalance != "0.00") ? single_price($clossingDrBalance) : "" }}</td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
    <tr>
        <td></td>
        <td></td>
        <td><strong>Grand Total</strong></td>
        <td>{{ single_price($drBalance + $clossingCrBalance) }}</td>
        <td>{{ single_price($crBalance + $clossingDrBalance) }}</td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
@else
    <tr>
        <td colspan="6">No Transaction Found.</td>
    </tr>
@endif