import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Button, Card } from 'antd';

import { getTournaments } from '../api/api';

import { TournamentList } from '../components';

export default function Tournaments() {
  const [tournaments, setTournaments] = useState([]);

  useEffect(() => {
    loadTournaments();
  }, []);

  const loadTournaments = async () => {
    const data = await getTournaments();
    setTournaments(data);
  };

  return (
    <Card title="Tournaments">
      <div className="container">
        <div className="flex justify-end items-center m-2">
          <Link to="/admin/import">
            <Button>Import Tournament</Button>
          </Link>
        </div>

        {tournaments.length === 0 ? <p>No tournaments imported yet.</p> : <TournamentList tournaments={tournaments} />}
      </div>
    </Card>
  );
}
