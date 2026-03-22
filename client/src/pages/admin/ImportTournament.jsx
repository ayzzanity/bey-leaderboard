import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button, Card, Input, List, Progress, Tag, Typography } from 'antd';

import { finalizeTournamentImports, importTournament } from '../../api/importApi';

export default function ImportTournament() {
  const [urls, setUrls] = useState('');
  const [loading, setLoading] = useState(false);

  const [message, setMessage] = useState();
  const [error, setError] = useState();
  const [results, setResults] = useState([]);
  const [progress, setProgress] = useState(null);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();

    setLoading(true);
    setError(null);
    setMessage(null);
    setResults([]);
    setProgress(null);

    try {
      const parsedUrls = urls
        .split(/[\r\n,]+/)
        .map((value) => value.trim())
        .filter(Boolean);

      const uniqueUrls = [...new Set(parsedUrls)];
      const nextResults = [];
      const successfulTournamentIds = [];
      const total = uniqueUrls.length;

      for (let index = 0; index < uniqueUrls.length; index += 1) {
        const url = uniqueUrls[index];

        setProgress({
          total,
          completed: index,
          currentLabel: url
        });

        try {
          const res = await importTournament(url, { deferPlayerStatsRebuild: true });
          nextResults.push(res);

          if (res.status === 200 && res.tournament_id) {
            successfulTournamentIds.push(res.tournament_id);
          }
        } catch (err) {
          const status = err.response?.status ?? 500;
          nextResults.push({
            status,
            url,
            message:
              status === 409
                ? (err.response?.data?.message ?? 'This tournament has already been imported.')
                : status === 422
                  ? 'One or more Challonge URLs are invalid'
                  : 'Server error while importing tournament',
            tournament_id: err.response?.data?.tournament_id,
            tournament_name: err.response?.data?.tournament_name
          });
        }

        setResults([...nextResults]);
        setProgress({
          total,
          completed: index + 1,
          currentLabel: url
        });
      }

      if (successfulTournamentIds.length > 0) {
        await finalizeTournamentImports();
      }

      setUrls('');
      setResults(nextResults);

      if (total === 1) {
        const singleResult = nextResults[0];

        if (singleResult?.status === 200) {
          setMessage(`Tournament "${singleResult.tournament_name}" imported successfully`);
          navigate(`/tournaments/${singleResult.tournament_id}`);
          return;
        }

        setError(singleResult?.message ?? 'Server error while importing tournament');
        return;
      }

      setMessage(`Imported ${successfulTournamentIds.length} of ${total} tournament(s).`);
    } catch (err) {
      if (err.response) {
        if (err.response.status === 409) {
          setError(err.response.data.message);
        } else if (err.response.status === 422) {
          setError('One or more Challonge URLs are invalid');
        } else {
          setError('Server error while importing tournament');
        }
      } else {
        setError('Network error');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container">
      <Card title="Import Challonge Tournament">
        <form onSubmit={handleSubmit} className="flex flex-col gap-3 mx-auto max-w-2xl">
          <Input.TextArea
            placeholder={'https://challonge.com/your_tournament\nhttps://challonge.com/another_tournament'}
            variant="filled"
            value={urls}
            onChange={(e) => setUrls(e.target.value)}
            autoSize={{ minRows: 4, maxRows: 10 }}
          />
          <Button htmlType="submit" type="primary" loading={loading} disabled={loading}>
            {loading ? 'Importing...' : 'Import Tournament(s)'}
          </Button>
        </form>

        {message && <Typography.Paragraph type="success">{message}</Typography.Paragraph>}
        {error && <Typography.Paragraph type="danger">{error}</Typography.Paragraph>}
        {progress && (
          <div className="mt-4">
            <Progress
              percent={progress.total > 0 ? Math.round((progress.completed / progress.total) * 100) : 100}
              status={loading ? 'active' : 'success'}
            />
            <Typography.Paragraph className="mb-0">
              {progress.total > 0
                ? `Processed ${progress.completed} of ${progress.total} tournament imports`
                : 'No tournaments queued for import.'}
            </Typography.Paragraph>
            {progress.currentLabel && <Typography.Text type="secondary">Current: {progress.currentLabel}</Typography.Text>}
          </div>
        )}

        {results.length > 0 && (
          <List
            className="mt-4"
            dataSource={results}
            renderItem={(item) => (
              <List.Item>
                <div className="flex w-full items-center justify-between gap-4">
                  <div>
                    <Typography.Text strong>{item.tournament_name ?? item.url}</Typography.Text>
                    <Typography.Paragraph className="mb-0">{item.message}</Typography.Paragraph>
                  </div>
                  <Typography.Paragraph className="mb-0">{item.url}</Typography.Paragraph>
                  <Tag color={item.status === 200 ? 'green' : item.status === 409 ? 'gold' : 'red'}>{item.status}</Tag>
                </div>
              </List.Item>
            )}
          />
        )}
      </Card>
    </div>
  );
}
