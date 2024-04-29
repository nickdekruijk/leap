<main class="leap-main">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
    </header>
    <div class="leap-resource">
        <div class="leap-index">
            <table class="leap-index-table">
                <tr class="leap-index-header">
                    @foreach ($this->index() as $attribute)
                        <th>{{ $attribute->label_index }}</th>
                    @endforeach
                </tr>
                @foreach ($this->getIndexData() as $row)
                    <tr class="leap-index-row">
                        @foreach ($row as $attribute)
                            <td>{{ $attribute }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</main>
