import { useState } from 'react';
import { importTournament } from '../../api/importApi';
import { useNavigate } from 'react-router-dom';

export default function ImportTournament() {
  const [url, setUrl] = useState('');
  const [loading, setLoading] = useState(false);

  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    setLoading(true);
    setError(null);
    setMessage(null);

    try {
      const res = await importTournament(url);

      setMessage(`Tournament "${res.tournament_name}" imported successfully`);

      setUrl('');
      navigate(`/tournaments/${res.tournament_id}`);
    } catch (err: any) {
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

      <form onSubmit={handleSubmit} className="import-form">
        <input
          type="text"
          placeholder="https://challonge.com/your_tournament"
          value={url}
          onChange={(e) => setUrl(e.target.value)}
          required
        />

        <button type="submit" disabled={loading}>
          {loading ? 'Importing...' : 'Import Tournament'}
        </button>
      </form>

      {message && <div className="success">{message}</div>}

      {error && <div className="error">{error}</div>}
    </div>
  );
}
