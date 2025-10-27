if ! curl -s http://localhost:9200 >/dev/null; then
	exit 1
fi

DAYS_TO_KEEP=2

for index in $(curl -s http://localhost:9200/_cat/indices?h=index | grep '^sw_shop'); do
  index_date=$(echo "$index" | grep -oE '[0-9]{8}')
  if [[ "$index_date" != "" ]]; then
    if [[ "$index_date" -lt "$(date -d "$DAYS_TO_KEEP days ago" +%Y%m%d)" ]]; then
      curl -s -X DELETE "http://localhost:9200/$index" >/dev/null
      echo "Deleted index $index"
    fi
  fi
done
