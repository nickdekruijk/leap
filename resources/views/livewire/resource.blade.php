<main class="leap-main">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
    </header>
    <div class="leap-resource">
        <div class="leap-listview">
            <table class="leap-listview-table">
                <tr class="leap-listview-header">
                    @foreach ($this->listColumns() as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
                @foreach ($listview as $row)
                    <tr class="leap-listview-row">
                        @foreach ($row as $column)
                            <td>{{ $column }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</main>
