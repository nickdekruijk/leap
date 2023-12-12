<div>
    <h1 class="header">{{ $this->getTitle() }}</h1>
    <div class="resource">
        <div class="listview">
            <table>
                <tr>
                    @foreach ($this->listColumns() as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
                @foreach ($listview as $row)
                    <tr>
                        @foreach ($row as $column)
                            <td>{{ $column }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</div>
