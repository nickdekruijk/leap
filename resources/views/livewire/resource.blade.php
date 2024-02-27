<main class="leap-main">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
    </header>
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
</main>
