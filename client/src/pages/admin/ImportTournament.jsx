import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button, Input, Space } from 'antd';

import { importTournament } from '../../api/importApi';

export default function ImportTournament() {
  const [url, setUrl] = useState('');
  const [loading, setLoading] = useState(false);

  const [message, setMessage] = useState();
  const [error, setError] = useState();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();

    setLoading(true);
    setError(null);
    setMessage(null);

    try {
      const res = await importTournament(url);

      setMessage(`Tournament "${res.tournament_name}" imported successfully`);

      setUrl('');
      navigate(`/tournaments/${res.tournament_id}`);
    } catch (err) {
      if (err.response) {
        if (err.response.status === 409) {
          setError(err.response.data.message);
        } else if (err.response.status === 422) {
          setError('Invalid Challonge URL');
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
      <h1>Import Challonge Tournament</h1>

      <form onSubmit={handleSubmit} className="flex gap-2 mx-auto max-w-md">
        <Input
          placeholder="https://challonge.com/your_tournament"
          variant="filled"
          value={url}
          onChange={(e) => setUrl(e.target.value)}
        />
        <Button htmlType="submit" type="primary" loading={loading} disabled={loading}>
          {loading ? 'Importing...' : 'Import Tournament'}
        </Button>
      </form>

      {message && <div className="success">{message}</div>}

      {error && <div className="error">{error}</div>}
    </div>
  );
}
