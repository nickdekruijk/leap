<main class="leap-main">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
    </header>
    <div class="leap-resource">
                    @foreach ($this->listColumns() as $column)
                        <th>{{ $column }}</th>
        <div class="leap-index">
            <table class="leap-index-table">
                <tr class="leap-index-header">
                    @endforeach
                </tr>
                @foreach ($listview as $row)
                        @foreach ($row as $column)
                            <td>{{ $column }}</td>
                    <tr class="leap-index-row">
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</main>
