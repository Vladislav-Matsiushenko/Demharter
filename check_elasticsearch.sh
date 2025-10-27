if ! curl -s http://localhost:9200 >/dev/null; then
	~/elasticsearch-7.2.0/bin/elasticsearch -d -p pid
	echo "Restarted"
fi
